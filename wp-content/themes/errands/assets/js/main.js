/**
 * ERRANDS theme behaviour.
 *
 * No dependencies. Everything degrades to working HTML if this fails to load:
 * the gallery buttons still show the images, the grid still shows every card.
 */
(function () {
	'use strict';

	var i18n = window.ERRANDS_I18N || { of: 'of' };

	/* ---------------------------------------------------------------
	 * Broken cover images fall back to the drawn cover
	 *
	 * The drawing is already in the markup underneath every cover, so this
	 * only has to reveal it. Registered first, and in the capture phase,
	 * because resource error events do not bubble.
	 * -------------------------------------------------------------- */

	function markBroken(img) {
		var holder = img.closest('.card__media, .project-hero');
		if (holder && !holder.classList.contains('is-broken')) {
			holder.classList.add('is-broken');
		}
	}

	document.addEventListener(
		'error',
		function (e) {
			var t = e.target;
			if (t && t.tagName === 'IMG') markBroken(t);
		},
		true
	);

	// Catch anything that already failed before this script ran.
	function sweepBrokenImages() {
		var imgs = document.querySelectorAll('.card__media img, .project-hero img');
		Array.prototype.forEach.call(imgs, function (img) {
			if (img.complete && img.naturalWidth === 0) markBroken(img);
		});
	}

	sweepBrokenImages();
	window.addEventListener('load', sweepBrokenImages);

	/* ---------------------------------------------------------------
	 * Theme toggle
	 * -------------------------------------------------------------- */

	function currentTheme() {
		var set = document.documentElement.getAttribute('data-theme');
		if (set) return set;
		return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	}

	var themeBtn = document.querySelector('.js-theme');
	if (themeBtn) {
		themeBtn.addEventListener('click', function () {
			var next = currentTheme() === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);
			try {
				localStorage.setItem('errands-theme', next);
			} catch (e) {
				/* Private mode or blocked storage: the choice just won't persist. */
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Mobile nav
	 * -------------------------------------------------------------- */

	var navBtn = document.querySelector('.js-nav');
	var navList = document.querySelector('.nav__list');
	if (navBtn && navList) {
		navBtn.addEventListener('click', function () {
			var open = navList.classList.toggle('is-open');
			navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ---------------------------------------------------------------
	 * Search overlay
	 * -------------------------------------------------------------- */

	var searchOverlay = document.querySelector('.js-search-overlay');
	var searchOpen = document.querySelector('.js-search-open');

	function closeSearch() {
		if (!searchOverlay) return;
		searchOverlay.classList.remove('is-open');
		if (searchOpen) searchOpen.focus();
	}

	if (searchOpen && searchOverlay) {
		searchOpen.addEventListener('click', function () {
			searchOverlay.classList.add('is-open');
			var input = searchOverlay.querySelector('input[type="search"]');
			if (input) input.focus();
		});
		searchOverlay.addEventListener('click', function (e) {
			if (e.target === searchOverlay) closeSearch();
		});
	}

	/* ---------------------------------------------------------------
	 * Archive filters
	 * -------------------------------------------------------------- */

	var filters = document.querySelector('.js-filters');
	var grid = document.querySelector('.js-grid');

	if (filters && grid) {
		var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));
		var emptyNote = document.querySelector('.js-filter-empty');

		filters.addEventListener('click', function (e) {
			var chip = e.target.closest('.chip');
			if (!chip) return;

			var kind = chip.getAttribute('data-filter');
			var value = chip.getAttribute('data-value');

			Array.prototype.forEach.call(filters.querySelectorAll('.chip'), function (c) {
				c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
			});

			var shown = 0;
			cards.forEach(function (card) {
				var match = true;

				if (kind === 'series') {
					match = (' ' + (card.getAttribute('data-series') || '') + ' ').indexOf(' ' + value + ' ') !== -1;
				} else if (kind === 'year') {
					match = card.getAttribute('data-year') === value;
				}

				card.classList.toggle('is-hidden', !match);
				if (match) shown++;
			});

			// Re-run the wide/std rhythm over what's left so rows stay whole.
			restripe(cards.filter(function (c) { return !c.classList.contains('is-hidden'); }));

			if (emptyNote) emptyNote.style.display = shown ? 'none' : '';
		});

		/**
		 * Reapply the 5-card [wide wide][std std std] pattern to the visible set.
		 */
		function restripe(visible) {
			var pattern = ['wide', 'wide', 'std', 'std', 'std'];
			visible.forEach(function (card, i) {
				card.classList.remove('card--wide', 'card--std', 'card--full');
				var span = pattern[i % pattern.length];
				if (i === visible.length - 1 && i % 5 === 0) span = 'full';
				card.classList.add('card--' + span);
			});
		}
	}

	/* ---------------------------------------------------------------
	 * Lightbox
	 * -------------------------------------------------------------- */

	var lb = document.querySelector('.js-lb');
	var galleries = Array.prototype.slice.call(document.querySelectorAll('.js-gal'));

	if (lb && galleries.length) {
		var lbImg = lb.querySelector('.js-lb-img');
		var lbCaption = lb.querySelector('.js-lb-caption');
		var lbCounter = lb.querySelector('.js-lb-counter');
		var lbClose = lb.querySelector('.js-lb-close');
		var lbPrev = lb.querySelector('.js-lb-prev');
		var lbNext = lb.querySelector('.js-lb-next');

		var items = [];
		galleries.forEach(function (gal) {
			Array.prototype.forEach.call(gal.querySelectorAll('.gal__open'), function (btn) {
				items.push({
					src: btn.getAttribute('data-full'),
					caption: btn.getAttribute('data-caption') || '',
					alt: (btn.querySelector('img') || {}).alt || '',
					btn: btn
				});
			});
		});

		var index = 0;
		var lastFocus = null;

		function show(i) {
			index = (i + items.length) % items.length;
			var item = items[index];
			lbImg.src = item.src;
			lbImg.alt = item.alt;
			lbCaption.textContent = item.caption;
			lbCounter.textContent = (index + 1) + ' ' + i18n.of + ' ' + items.length;

			// Warm the neighbours so arrowing through feels instant.
			[index + 1, index - 1].forEach(function (n) {
				var neighbour = items[(n + items.length) % items.length];
				if (neighbour) new Image().src = neighbour.src;
			});
		}

		function open(i) {
			lastFocus = document.activeElement;
			show(i);
			lb.classList.add('is-open');
			document.body.style.overflow = 'hidden';
			lbClose.focus();
		}

		function close() {
			lb.classList.remove('is-open');
			document.body.style.overflow = '';
			if (lastFocus) lastFocus.focus();
		}

		items.forEach(function (item, i) {
			item.btn.addEventListener('click', function () { open(i); });
		});

		lbClose.addEventListener('click', close);
		lbPrev.addEventListener('click', function () { show(index - 1); });
		lbNext.addEventListener('click', function () { show(index + 1); });

		lb.addEventListener('click', function (e) {
			// Clicking the backdrop or the image itself advances out / along.
			if (e.target === lb || e.target.classList.contains('lb__stage')) close();
		});

		// Swipe on touch devices.
		var touchX = null;
		lb.addEventListener('touchstart', function (e) {
			touchX = e.changedTouches[0].clientX;
		}, { passive: true });
		lb.addEventListener('touchend', function (e) {
			if (touchX === null) return;
			var dx = e.changedTouches[0].clientX - touchX;
			if (Math.abs(dx) > 45) show(dx < 0 ? index + 1 : index - 1);
			touchX = null;
		}, { passive: true });

		document.addEventListener('keydown', function (e) {
			if (!lb.classList.contains('is-open')) return;
			if (e.key === 'Escape') close();
			else if (e.key === 'ArrowLeft') show(index - 1);
			else if (e.key === 'ArrowRight') show(index + 1);
		});
	}

	/* ---------------------------------------------------------------
	 * Global Esc: close whichever layer is open
	 * -------------------------------------------------------------- */

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		if (searchOverlay && searchOverlay.classList.contains('is-open')) closeSearch();
		if (navList && navList.classList.contains('is-open')) {
			navList.classList.remove('is-open');
			if (navBtn) navBtn.setAttribute('aria-expanded', 'false');
		}
	});

	/* ---------------------------------------------------------------
	 * Reveal-on-scroll for cards (subtle, and skipped if reduced motion)
	 * -------------------------------------------------------------- */

	if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
		var targets = document.querySelectorAll('.card, .gal__item');
		Array.prototype.forEach.call(targets, function (el) {
			el.style.opacity = '0';
			el.style.transform = 'translateY(14px)';
			el.style.transition = 'opacity .6s cubic-bezier(.22,.61,.36,1), transform .6s cubic-bezier(.22,.61,.36,1)';
		});

		var reveal = function (el) {
			el.style.opacity = '';
			el.style.transform = '';
		};

		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				reveal(entry.target);
				io.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -8% 0px' });

		Array.prototype.forEach.call(targets, function (el) { io.observe(el); });

		// Failsafe: never leave content hidden. If the observer has not fired
		// for something (print, headless capture, an odd viewport), show it.
		setTimeout(function () {
			io.disconnect();
			Array.prototype.forEach.call(targets, reveal);
		}, 2500);
	}
})();
