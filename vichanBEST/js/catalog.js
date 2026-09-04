/*
 * catalog.js — sort / size controls (vanilla JS, no jQuery/MixItUp).
 */
(function () {
	if (typeof active_page === 'undefined' || active_page !== 'catalog') {
		return;
	}

	function loadState() {
		try {
			return JSON.parse(localStorage.catalog || '{}') || {};
		} catch (e) {
			return {};
		}
	}

	function saveState(state) {
		localStorage.catalog = JSON.stringify(state);
	}

	function sortGrid(mode) {
		var grid = document.getElementById('Grid');
		if (!grid) {
			return;
		}
		var items = Array.prototype.slice.call(grid.querySelectorAll('.mix'));
		items.sort(function (a, b) {
			var stickyA = a.getAttribute('data-sticky') === 'true' ? 1 : 0;
			var stickyB = b.getAttribute('data-sticky') === 'true' ? 1 : 0;
			if (stickyA !== stickyB) {
				return stickyB - stickyA;
			}
			if (mode === 'random' || mode === 'random:desc') {
				return Math.random() - 0.5;
			}
			var key = 'bump';
			var desc = true;
			if (mode.indexOf('time') === 0) {
				key = 'time';
			} else if (mode.indexOf('reply') === 0) {
				key = 'reply';
			} else if (mode.indexOf('bump') === 0) {
				key = 'bump';
			}
			var va = parseInt(a.getAttribute('data-' + key), 10) || 0;
			var vb = parseInt(b.getAttribute('data-' + key), 10) || 0;
			return desc ? (vb - va) : (va - vb);
		});
		items.forEach(function (el) {
			grid.appendChild(el);
		});
	}

	function setImageSize(size) {
		document.querySelectorAll('.grid-li').forEach(function (el) {
			el.classList.remove('grid-size-vsmall', 'grid-size-small', 'grid-size-large');
			el.classList.add('grid-size-' + size);
		});
	}

	function init() {
		var state = loadState();
		var sortBy = document.getElementById('sort_by');
		var imageSize = document.getElementById('image_size');

		if (sortBy) {
			if (state.sort_by) {
				sortBy.value = state.sort_by;
			}
			sortBy.addEventListener('change', function () {
				state.sort_by = sortBy.value;
				saveState(state);
				sortGrid(sortBy.value);
			});
			sortGrid(sortBy.value);
		}

		if (imageSize) {
			if (state.image_size) {
				imageSize.value = state.image_size;
			}
			setImageSize(imageSize.value);
			imageSize.addEventListener('change', function () {
				state.image_size = imageSize.value;
				saveState(state);
				setImageSize(imageSize.value);
			});
		}

		document.querySelectorAll('div.thread').forEach(function (thread) {
			thread.addEventListener('click', function (e) {
				// expand overflow if clipped (legacy UX)
				if (window.getComputedStyle(thread).overflowY === 'hidden') {
					thread.style.overflowY = 'auto';
					thread.style.maxHeight = 'none';
					e.preventDefault();
				}
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
