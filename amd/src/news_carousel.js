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
 * Dashboard news carousel: ticks through admin-authored stories on a timer. The markup
 * (see templates/news_carousel.mustache) is already fully rendered server-side with the
 * first slide visible - this module only adds the timer-driven rotation and dot-click
 * navigation on top of it, so the carousel still shows its first story if this fails to
 * load.
 *
 * hook_callbacks::before_standard_top_of_body_html_generation() renders the carousel
 * right after <body>, which is theme-agnostic but sits behind a fixed/sticky theme
 * header on themes that use one. {@see relocate} moves it in front of the dashboard's
 * own #page-content instead - present in Boost and Boost-derived themes regardless of
 * whether the viewer has any blocks configured or is in editing mode (unlike the
 * page-top block region itself, which only renders in those cases) - falling back to
 * staying put, still fully working, on any theme without that element.
 *
 * @module     local_communications/news_carousel
 * @copyright  2026 Tom Cripps <tom.cripps@port.ac.uk>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    // Falls back to the site default (see settings.php) if init() is ever called
    // without params - it always is in practice, hook_callbacks.php passes it.
    var DEFAULT_INTERVAL_MS = 6000;

    /**
     * Moves the carousel from the top of <body> to right before #page-content, if the
     * current theme renders one. Placed as a sibling immediately before it, not as its
     * first child: #page-content is a Bootstrap grid row there, and an unsized child
     * dropped straight into a row can end up squashed or overlapping instead of running
     * full width. Does nothing (leaving the carousel where the server rendered it) on a
     * theme without this element.
     *
     * @param {Element} root
     */
    var relocate = function(root) {
        var pageContent = document.getElementById('page-content');
        if (pageContent) {
            pageContent.insertAdjacentElement('beforebegin', root);
        }
    };

    /**
     * Initialise the dashboard news carousel, if present on this page.
     *
     * @param {Object} [params]
     * @param {Number} [params.intervalms] Milliseconds each slide shows before auto-advancing.
     */
    var init = function(params) {
        var intervalMs = (params && params.intervalms) || DEFAULT_INTERVAL_MS;

        var root = document.getElementById('local-communications-news-carousel');
        if (!root || root.dataset.carouselBound === '1') {
            return;
        }
        root.dataset.carouselBound = '1';

        relocate(root);

        var slides = Array.prototype.slice.call(root.querySelectorAll('.local-communications__news-slide'));
        var dots = Array.prototype.slice.call(root.querySelectorAll('.local-communications__news-dot'));
        var prevBtn = root.querySelector('[data-action="prev"]');
        var nextBtn = root.querySelector('[data-action="next"]');
        var playPauseBtn = root.querySelector('[data-action="playpause"]');
        var playPauseIcon = playPauseBtn ? playPauseBtn.querySelector('[data-role="playpause-icon"]') : null;
        if (slides.length < 2) {
            return;
        }

        var current = slides.findIndex(function(slide) {
            return slide.classList.contains('local-communications__news-slide--active');
        });
        current = current === -1 ? 0 : current;

        var timer = null;
        // Distinct from the timer itself: set only by the play/pause button, so an
        // explicit pause sticks even after the mouse/focus leaves the carousel, rather
        // than being silently overridden by the hover/focus auto-resume below.
        var userPaused = false;

        var goTo = function(index) {
            slides[current].classList.remove('local-communications__news-slide--active');
            if (dots[current]) {
                dots[current].classList.remove('local-communications__news-dot--active');
                dots[current].removeAttribute('aria-current');
            }

            current = (index + slides.length) % slides.length;

            slides[current].classList.add('local-communications__news-slide--active');
            if (dots[current]) {
                dots[current].classList.add('local-communications__news-dot--active');
                dots[current].setAttribute('aria-current', 'true');
            }
        };

        var next = function() {
            goTo(current + 1);
        };

        var prev = function() {
            goTo(current - 1);
        };

        var stop = function() {
            window.clearInterval(timer);
            timer = null;
        };

        var start = function() {
            if (timer || userPaused || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            timer = window.setInterval(next, intervalMs);
        };

        // Manual navigation (dots/prev/next) resets the countdown rather than fighting
        // it, but never overrides an explicit pause - start() already guards on that.
        var restart = function() {
            stop();
            start();
        };

        dots.forEach(function(dot) {
            dot.addEventListener('click', function() {
                goTo(parseInt(dot.dataset.index, 10) || 0);
                restart();
            });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                prev();
                restart();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                next();
                restart();
            });
        }

        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', function() {
                userPaused = !userPaused;
                playPauseBtn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
                playPauseBtn.setAttribute(
                    'aria-label', userPaused ? playPauseBtn.dataset.playlabel : playPauseBtn.dataset.pauselabel
                );
                if (playPauseIcon) {
                    playPauseIcon.classList.toggle('fa-play', userPaused);
                    playPauseIcon.classList.toggle('fa-pause', !userPaused);
                }
                if (userPaused) {
                    stop();
                } else {
                    start();
                }
            });
        }

        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);

        start();
    };

    return {init: init};
});
