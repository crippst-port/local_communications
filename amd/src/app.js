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
    'jquery',
    'core/templates',
    'core/modal_factory',
    'core/modal_events',
    'core/str',
    'core/notification',
], function($, Templates, ModalFactory, ModalEvents, Str, Notification) {

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

        var showStep = function(root, step) {
            root.find('[data-step]').attr('hidden', 'hidden');
            root.find('[data-step="' + step + '"]').removeAttr('hidden');
        };

        var resetOtherInput = function(root) {
            state.categoryOther = false;
            root.find('[data-role="category-other-wrap"]').attr('hidden', 'hidden');
            root.find('[data-role="category-other-text"]').val('');
            root.find('[data-action="category-other-continue"]')
                .attr('hidden', 'hidden')
                .prop('disabled', true);
        };

        var resetForm = function(root) {
            state.sentiment = null;
            state.category = null;
            resetOtherInput(root);
            root.find('[data-role="feedbacktext"]').val('');
            root.find('[data-role="anonymous"]').prop('checked', false);
            root.find('[data-role="error"]').attr('hidden', 'hidden').text('');
            showStep(root, '1');
        };

        var populateStrings = function(root, s) {
            root.find('[data-role="label-happy"]').text(params.labelhappy || s.sentiment_happy);
            root.find('[data-role="label-neutral"]').text(params.labelneutral || s.sentiment_neutral);
            root.find('[data-role="label-sad"]').text(params.labelsad || s.sentiment_sad);
            root.find('[data-role="category-heading"]').text(s.category_heading);
            root.find('[data-role="category-label-other"]').text(s.category_other);
            root.find('[data-role="category-other-text"]').attr('placeholder', s.category_other_placeholder);
            root.find('[data-action="category-back"]').text(s.back);
            root.find('[data-action="category-skip"]').text(s.category_skip);
            root.find('[data-action="category-other-continue"]').text(s.continue);
            root.find('[data-role="feedbacktext"]').attr('placeholder', s.placeholder_feedback);
            root.find('[data-role="anonymous-label"]').text(s.anonymous_label);
            root.find('[data-action="back"]').text(s.back);
            root.find('[data-action="submit"]').text(s.submit);
            root.find('[data-action="close"]').text(s.close);
            root.find('[data-role="thankyou-title"]').text(s.thankyou_title);
            root.find('[data-role="thankyou-body"]').text(s.thankyou_body);
            root.find('[data-role="neverask-prefix"]').text(s.neverask_prefix);
            root.find('[data-role="neverask-linktext"]').text(s.neverask_linktext);
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

        var showError = function(root, message) {
            root.find('[data-role="error"]').text(message).removeAttr('hidden');
        };

        var goToComment = function(root) {
            showStep(root, '3');
            root.find('[data-role="feedbacktext"]').trigger('focus');
        };

        var submitFeedback = function(root, s) {
            var text = root.find('[data-role="feedbacktext"]').val().trim();
            if (!text) {
                showError(root, s.error_empty);
                return;
            }

            var submitBtn = root.find('[data-action="submit"]');
            submitBtn.prop('disabled', true).text(s.submitting);
            root.find('[data-role="error"]').attr('hidden', 'hidden');

            var context = buildContext(params);
            context.sentiment = state.sentiment;
            context.category = state.category || '';
            context.categoryother = state.categoryOther ? 1 : 0;
            context.feedbacktext = text;
            context.anonymous = root.find('[data-role="anonymous"]').is(':checked') ? 1 : 0;

            $.ajax({
                url: M.cfg.wwwroot + '/local/communications/ajax/submit.php',
                method: 'POST',
                dataType: 'json',
                data: $.extend({sesskey: M.cfg.sesskey}, context),
            }).done(function(response) {
                submitBtn.prop('disabled', false).text(s.submit);
                if (response && response.success) {
                    showStep(root, '4');
                } else {
                    showError(root, (response && response.error) || s.error_generic);
                }
            }).fail(function() {
                submitBtn.prop('disabled', false).text(s.submit);
                showError(root, s.error_generic);
            });
        };

        var getModal = function() {
            if (!modalPromise) {
                // Render both templates and fetch strings *before* creating the modal,
                // rather than handing ModalFactory unresolved body/footer promises: that
                // would resolve the create() promise before the HTML actually lands in
                // the DOM, so code populating labels/text would run against an empty root.
                modalPromise = $.when(
                    Templates.renderForPromise('local_communications/modal_body', {
                        categories: params.categories.map(function(value) {
                            return {value: value};
                        }),
                    }),
                    Templates.renderForPromise('local_communications/modal_footer', {}),
                    getStrings()
                ).then(function(bodyData, footerData, s) {
                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: params.campaignmodaltitle || s.modaltitle,
                        body: bodyData.html,
                        footer: footerData.html,
                    }).then(function(modal) {
                        var root = modal.getRoot();
                        populateStrings(root, s);

                        if (params.campaignintro) {
                            root.find('[data-role="intro"]').text(params.campaignintro).removeAttr('hidden');
                        }

                        root.on('click', '[data-sentiment]', function(e) {
                            state.sentiment = $(e.currentTarget).data('sentiment');
                            root.find('[data-role="prompt"]').text(promptFor(state.sentiment, s));
                            if (params.skiptopicstep) {
                                state.category = null;
                                state.categoryOther = false;
                                goToComment(root);
                            } else {
                                showStep(root, '2');
                            }
                        });

                        root.on('click', '[data-category]', function(e) {
                            state.category = $(e.currentTarget).data('category');
                            state.categoryOther = false;
                            goToComment(root);
                        });

                        root.on('click', '[data-action="category-other"]', function() {
                            root.find('[data-role="category-other-wrap"]').removeAttr('hidden');
                            root.find('[data-action="category-other-continue"]').removeAttr('hidden');
                            root.find('[data-role="category-other-text"]').trigger('focus');
                        });

                        root.on('input', '[data-role="category-other-text"]', function(e) {
                            var hastext = $(e.currentTarget).val().trim().length > 0;
                            root.find('[data-action="category-other-continue"]').prop('disabled', !hastext);
                        });

                        root.on('click', '[data-action="category-other-continue"]', function() {
                            var text = root.find('[data-role="category-other-text"]').val().trim();
                            if (!text) {
                                return;
                            }
                            state.category = text;
                            state.categoryOther = true;
                            goToComment(root);
                        });

                        root.on('click', '[data-action="category-skip"]', function() {
                            state.category = null;
                            state.categoryOther = false;
                            goToComment(root);
                        });

                        root.on('click', '[data-action="category-back"]', function() {
                            showStep(root, '1');
                        });

                        root.on('click', '[data-action="back"]', function() {
                            showStep(root, params.skiptopicstep ? '1' : '2');
                        });

                        root.on('click', '[data-action="submit"]', function() {
                            submitFeedback(root, s);
                        });

                        root.on('click', '[data-action="close"]', function() {
                            modal.hide();
                        });

                        root.on('click', '[data-action="neverask"]', function() {
                            if (wrap) {
                                wrap.hidden = true;
                            }
                            modal.hide();
                            $.ajax({
                                url: M.cfg.wwwroot + '/local/communications/ajax/neverask.php',
                                method: 'POST',
                                dataType: 'json',
                                data: {sesskey: M.cfg.sesskey, campaignid: params.campaignid},
                            }).fail(Notification.exception);
                        });

                        root.on(ModalEvents.hidden, function() {
                            trigger.setAttribute('aria-expanded', 'false');
                            resetForm(root);
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
