// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Floating course feedback widget: happy/neutral/sad -> area (optional, admin-configurable
 * presets or free text via "Other") -> comment -> submit.
 *
 * @module     local_communications/app
 * @copyright  2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/templates',
    'core/modal_factory',
    'core/modal_events',
    'core/str',
    'core/notification',
], function(Templates, ModalFactory, ModalEvents, Str, Notification) {

    var STRING_KEYS = [
        'sentiment_happy',
        'sentiment_neutral',
        'sentiment_sad',
        'prompt_happy',
        'prompt_neutral',
        'prompt_sad',
        'placeholder_feedback',
        'anonymous_label',
        'back',
        'submit',
        'submitting',
        'thankyou_title',
        'thankyou_body',
        'close',
        'error_generic',
        'error_empty',
        'modaltitle',
        'category_heading',
        'category_other',
        'category_other_placeholder',
        'category_skip',
        'continue',
        'neverask_prefix',
        'neverask_linktext',
    ];

    /**
     * Build the config sent to the server, capturing as much page context
     * as is reasonably available client-side. The server re-validates the
     * course/module identity itself; only descriptive fields come from here.
     *
     * @param {Object} params Config passed from PHP on page load.
     * @return {Object}
     */
    var buildContext = function(params) {
        return {
            courseid: params.courseid,
            cmid: params.cmid,
            campaignid: params.campaignid,
            pagetype: params.pagetype,
            breadcrumb: params.breadcrumb || '',
            pageurl: window.location.href,
            pagetitle: document.title,
            referrer: document.referrer || '',
            useragent: navigator.userAgent || '',
            screenwidth: window.screen ? window.screen.width : 0,
            screenheight: window.screen ? window.screen.height : 0,
            lang: navigator.language || '',
        };
    };

    /**
     * POST a plain object to a Moodle ajax endpoint as form-encoded data and
     * decode the JSON response.
     *
     * @param {String} url
     * @param {Object} data
     * @return {Promise}
     */
    var postForm = function(url, data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function(key) {
            body.append(key, data[key]);
        });
        return fetch(url, {
            method: 'POST',
            body: body,
        }).then(function(response) {
            return response.json();
        });
    };

    /**
     * Initialise the floating feedback trigger and its modal.
     *
     * @param {Object} params
     */
    var init = function(params) {
        var wrap = document.getElementById('local-communications-wrap');
        var trigger = document.getElementById('local-communications-trigger');
        if (!trigger || trigger.dataset.feedbackBound === '1') {
            return;
        }
        trigger.dataset.feedbackBound = '1';

        var strings = null;
        var modalPromise = null;
        var state = {sentiment: null, category: null, categoryOther: false};

        var getStrings = function() {
            if (!strings) {
                strings = Str.get_strings(STRING_KEYS.map(function(key) {
                    return {key: key, component: 'local_communications'};
                })).then(function(results) {
                    var map = {};
                    STRING_KEYS.forEach(function(key, index) {
                        map[key] = results[index];
                    });
                    return map;
                });
            }
            return strings;
        };

        var showStep = function(rootEl, step) {
            rootEl.querySelectorAll('[data-step]').forEach(function(el) {
                el.hidden = true;
            });
            rootEl.querySelectorAll('[data-step="' + step + '"]').forEach(function(el) {
                el.hidden = false;
            });
        };

        var resetOtherInput = function(rootEl) {
            state.categoryOther = false;
            rootEl.querySelector('[data-role="category-other-wrap"]').hidden = true;
            rootEl.querySelector('[data-role="category-other-text"]').value = '';
            var continueBtn = rootEl.querySelector('[data-action="category-other-continue"]');
            continueBtn.hidden = true;
            continueBtn.disabled = true;
        };

        var resetForm = function(rootEl) {
            state.sentiment = null;
            state.category = null;
            resetOtherInput(rootEl);
            rootEl.querySelector('[data-role="feedbacktext"]').value = '';
            rootEl.querySelector('[data-role="anonymous"]').checked = false;
            var error = rootEl.querySelector('[data-role="error"]');
            error.hidden = true;
            error.textContent = '';
            showStep(rootEl, '1');
        };

        var populateStrings = function(rootEl, s) {
            rootEl.querySelector('[data-role="label-happy"]').textContent = params.labelhappy || s.sentiment_happy;
            rootEl.querySelector('[data-role="label-neutral"]').textContent = params.labelneutral || s.sentiment_neutral;
            rootEl.querySelector('[data-role="label-sad"]').textContent = params.labelsad || s.sentiment_sad;
            rootEl.querySelector('[data-role="category-heading"]').textContent = s.category_heading;
            rootEl.querySelector('[data-role="category-label-other"]').textContent = s.category_other;
            rootEl.querySelector('[data-role="category-other-text"]').placeholder = s.category_other_placeholder;
            rootEl.querySelector('[data-action="category-back"]').textContent = s.back;
            rootEl.querySelector('[data-action="category-skip"]').textContent = s.category_skip;
            rootEl.querySelector('[data-action="category-other-continue"]').textContent = s.continue;
            rootEl.querySelector('[data-role="feedbacktext"]').placeholder = s.placeholder_feedback;
            rootEl.querySelector('[data-role="anonymous-label"]').textContent = s.anonymous_label;
            rootEl.querySelector('[data-action="back"]').textContent = s.back;
            rootEl.querySelector('[data-action="submit"]').textContent = s.submit;
            rootEl.querySelector('[data-action="close"]').textContent = s.close;
            rootEl.querySelector('[data-role="thankyou-title"]').textContent = s.thankyou_title;
            rootEl.querySelector('[data-role="thankyou-body"]').textContent = s.thankyou_body;
            rootEl.querySelector('[data-role="neverask-prefix"]').textContent = s.neverask_prefix;
            rootEl.querySelector('[data-role="neverask-linktext"]').textContent = s.neverask_linktext;
        };

        var promptFor = function(sentiment, s) {
            if (sentiment === 'happy') {
                return s.prompt_happy;
            }
            if (sentiment === 'sad') {
                return s.prompt_sad;
            }
            return s.prompt_neutral;
        };

        var showError = function(rootEl, message) {
            var error = rootEl.querySelector('[data-role="error"]');
            error.textContent = message;
            error.hidden = false;
        };

        var goToComment = function(rootEl) {
            showStep(rootEl, '3');
            rootEl.querySelector('[data-role="feedbacktext"]').focus();
        };

        var submitFeedback = function(rootEl, s) {
            var text = rootEl.querySelector('[data-role="feedbacktext"]').value.trim();
            if (!text) {
                showError(rootEl, s.error_empty);
                return;
            }

            var submitBtn = rootEl.querySelector('[data-action="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = s.submitting;
            rootEl.querySelector('[data-role="error"]').hidden = true;

            var context = buildContext(params);
            context.sentiment = state.sentiment;
            context.category = state.category || '';
            context.categoryother = state.categoryOther ? 1 : 0;
            context.feedbacktext = text;
            context.anonymous = rootEl.querySelector('[data-role="anonymous"]').checked ? 1 : 0;
            context.sesskey = M.cfg.sesskey;

            postForm(M.cfg.wwwroot + '/local/communications/ajax/submit.php', context).then(function(response) {
                submitBtn.disabled = false;
                submitBtn.textContent = s.submit;
                if (response && response.success) {
                    showStep(rootEl, '4');
                } else {
                    showError(rootEl, (response && response.error) || s.error_generic);
                }
                return response;
            }).catch(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = s.submit;
                showError(rootEl, s.error_generic);
            });
        };

        var getModal = function() {
            if (!modalPromise) {
                // Render both templates and fetch strings *before* creating the modal,
                // rather than handing ModalFactory unresolved body/footer promises: that
                // would resolve the create() promise before the HTML actually lands in
                // the DOM, so code populating labels/text would run against an empty root.
                modalPromise = Promise.all([
                    Templates.renderForPromise('local_communications/modal_body', {
                        categories: params.categories.map(function(value) {
                            return {value: value};
                        }),
                    }),
                    Templates.renderForPromise('local_communications/modal_footer', {}),
                    getStrings(),
                ]).then(function(results) {
                    var bodyData = results[0];
                    var footerData = results[1];
                    var s = results[2];
                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: params.campaignmodaltitle || s.modaltitle,
                        body: bodyData.html,
                        footer: footerData.html,
                    }).then(function(modal) {
                        var root = modal.getRoot();
                        var rootEl = root[0];
                        populateStrings(rootEl, s);

                        if (params.campaignintro) {
                            var intro = rootEl.querySelector('[data-role="intro"]');
                            intro.textContent = params.campaignintro;
                            intro.hidden = false;
                        }

                        rootEl.addEventListener('click', function(e) {
                            var target = e.target.closest('[data-sentiment]');
                            if (!target || !rootEl.contains(target)) {
                                return;
                            }
                            state.sentiment = target.dataset.sentiment;
                            rootEl.querySelector('[data-role="prompt"]').textContent = promptFor(state.sentiment, s);
                            if (params.skiptopicstep) {
                                state.category = null;
                                state.categoryOther = false;
                                goToComment(rootEl);
                            } else {
                                showStep(rootEl, '2');
                            }
                        });

                        rootEl.addEventListener('click', function(e) {
                            var target = e.target.closest('[data-category]');
                            if (!target || !rootEl.contains(target)) {
                                return;
                            }
                            state.category = target.dataset.category;
                            state.categoryOther = false;
                            goToComment(rootEl);
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="category-other"]')) {
                                return;
                            }
                            rootEl.querySelector('[data-role="category-other-wrap"]').hidden = false;
                            rootEl.querySelector('[data-action="category-other-continue"]').hidden = false;
                            rootEl.querySelector('[data-role="category-other-text"]').focus();
                        });

                        rootEl.addEventListener('input', function(e) {
                            if (!e.target.closest('[data-role="category-other-text"]')) {
                                return;
                            }
                            var hastext = e.target.value.trim().length > 0;
                            rootEl.querySelector('[data-action="category-other-continue"]').disabled = !hastext;
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="category-other-continue"]')) {
                                return;
                            }
                            var text = rootEl.querySelector('[data-role="category-other-text"]').value.trim();
                            if (!text) {
                                return;
                            }
                            state.category = text;
                            state.categoryOther = true;
                            goToComment(rootEl);
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="category-skip"]')) {
                                return;
                            }
                            state.category = null;
                            state.categoryOther = false;
                            goToComment(rootEl);
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="category-back"]')) {
                                return;
                            }
                            showStep(rootEl, '1');
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="back"]')) {
                                return;
                            }
                            showStep(rootEl, params.skiptopicstep ? '1' : '2');
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="submit"]')) {
                                return;
                            }
                            submitFeedback(rootEl, s);
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="close"]')) {
                                return;
                            }
                            modal.hide();
                        });

                        rootEl.addEventListener('click', function(e) {
                            if (!e.target.closest('[data-action="neverask"]')) {
                                return;
                            }
                            if (wrap) {
                                wrap.hidden = true;
                            }
                            modal.hide();
                            postForm(M.cfg.wwwroot + '/local/communications/ajax/neverask.php', {
                                sesskey: M.cfg.sesskey,
                                campaignid: params.campaignid,
                            }).catch(Notification.exception);
                        });

                        // ModalEvents are fired via the modal root's jQuery-based event
                        // bus (see core/modal), so this listener stays on the jQuery
                        // wrapper rather than the native element - the same pattern core
                        // itself uses (e.g. lib/amd/src/tag.js).
                        root.on(ModalEvents.hidden, function() {
                            trigger.setAttribute('aria-expanded', 'false');
                            resetForm(rootEl);
                        });

                        return modal;
                    });
                }).catch(function(error) {
                    modalPromise = null;
                    Notification.exception(error);
                });
            }

            return modalPromise;
        };

        trigger.addEventListener('click', function() {
            getModal().then(function(modal) {
                if (modal) {
                    trigger.setAttribute('aria-expanded', 'true');
                    modal.show();
                }
                return modal;
            }).catch(Notification.exception);
        });
    };

    return {init: init};
});
