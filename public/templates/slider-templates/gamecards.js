/**
 * Game Cards slider — vanilla carousel controller.
 *
 * Turns each .pp-game-grid into a horizontal scroll-snap carousel: reveals the
 * prev/next arrows only when the track overflows, auto-scrolls to the next
 * upcoming game (data-next-index), and keeps arrow disabled state in sync.
 *
 * Safe to run repeatedly: the admin preview replaces #pp-game-slider-preview's
 * innerHTML and re-runs every gameScheduleInitializers entry, so per-element
 * listeners are guarded with a dataset flag.
 */
(function () {
	'use strict';

	var resizeBound = false;

	function initGameCards() {
		var wraps = document.querySelectorAll('.pp-gamecards');
		for (var i = 0; i < wraps.length; i++) {
			setupGameCards(wraps[i]);
		}

		if (!resizeBound) {
			resizeBound = true;
			window.addEventListener('resize', refreshAll);
		}
	}

	function setupGameCards(wrap) {
		var track = wrap.querySelector('.pp-game-grid');
		if (!track) {
			return; // empty-state has no track
		}

		var prev = wrap.querySelector('.pp-gamecards-prev');
		var next = wrap.querySelector('.pp-gamecards-next');

		// Position on the next upcoming game (runs on every (re)init).
		scrollToNextIndex(track);
		refreshNav(wrap, track);

		if (wrap.dataset.ppGamecardsInit) {
			return; // listeners already bound for this element
		}
		wrap.dataset.ppGamecardsInit = '1';

		var page = function () {
			return Math.max(track.clientWidth * 0.9, 1);
		};

		if (prev) {
			prev.addEventListener('click', function () {
				track.scrollBy({ left: -page(), behavior: 'smooth' });
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				track.scrollBy({ left: page(), behavior: 'smooth' });
			});
		}

		track.addEventListener('scroll', function () {
			refreshNav(wrap, track);
		});
	}

	function refreshAll() {
		var wraps = document.querySelectorAll('.pp-gamecards');
		for (var i = 0; i < wraps.length; i++) {
			var track = wraps[i].querySelector('.pp-game-grid');
			if (track) {
				refreshNav(wraps[i], track);
			}
		}
	}

	function refreshNav(wrap, track) {
		var prev = wrap.querySelector('.pp-gamecards-prev');
		var next = wrap.querySelector('.pp-gamecards-next');

		var maxScroll = track.scrollWidth - track.clientWidth;
		var overflow = maxScroll > 1;

		wrap.classList.toggle('pp-gamecards--overflow', overflow);

		if (prev) {
			prev.disabled = !overflow || track.scrollLeft <= 1;
		}
		if (next) {
			next.disabled = !overflow || track.scrollLeft >= maxScroll - 1;
		}
	}

	function scrollToNextIndex(track) {
		var cards = track.children;
		if (!cards.length) {
			return;
		}

		var idx = parseInt(track.getAttribute('data-next-index'), 10) || 0;
		var target = cards[Math.min(idx, cards.length - 1)];
		var first = cards[0];
		if (!target || !first) {
			return;
		}

		// Jump without animation for the initial position.
		var behavior = track.style.scrollBehavior;
		track.style.scrollBehavior = 'auto';
		track.scrollLeft = target.offsetLeft - first.offsetLeft;
		track.style.scrollBehavior = behavior || '';
	}

	function start() {
		initGameCards();
		// Admin-only: register for re-init after preview AJAX swaps.
		if (typeof window.gameScheduleInitializers !== 'undefined') {
			window.gameScheduleInitializers.push(initGameCards);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
