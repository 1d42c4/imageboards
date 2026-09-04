/*
 * inline-expanding.js — click thumbnail to grow full size; click again to shrink.
 * Vanilla JS, no jQuery.
 */
(function () {
	'use strict';

	function isImageAnchor(a) {
		if (!a || a.tagName !== 'A' || !a.href) {
			return false;
		}
		// Non-image attachments use class "file" (icon only)
		if (a.classList.contains('file') && !a.classList.contains('file-link')) {
			return false;
		}
		var img = a.querySelector('img.post-image');
		return !!img;
	}

	function getThumb(a) {
		return a.querySelector('img.post-image');
	}

	function getFull(a) {
		return a.querySelector('img.full-image');
	}

	function collapse(a) {
		var thumb = getThumb(a);
		var full = getFull(a);
		if (full) {
			full.remove();
		}
		if (thumb) {
			thumb.style.display = '';
			thumb.style.opacity = '';
		}
		a.classList.remove('expanded');
		a.dataset.expanded = 'false';
	}

	function expand(a) {
		var thumb = getThumb(a);
		if (!thumb) {
			return;
		}

		// Already expanded
		if (a.dataset.expanded === 'true') {
			return;
		}

		a.dataset.expanded = 'true';
		a.classList.add('expanded');
		thumb.style.opacity = '0.4';

		var full = document.createElement('img');
		full.className = 'full-image';
		full.alt = '';
		full.style.display = 'none';

		full.addEventListener('load', function () {
			thumb.style.display = 'none';
			thumb.style.opacity = '';
			full.style.display = '';
		});
		full.addEventListener('error', function () {
			// Fall back to opening original if load fails
			collapse(a);
			window.open(a.href, '_blank');
		});

		a.appendChild(full);
		full.src = a.href;
	}

	function onClick(e) {
		// Middle-click / ctrl-click / meta-click → open real link in new tab
		if (e.button === 1 || e.ctrlKey || e.metaKey || e.shiftKey) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();

		var a = e.currentTarget;
		if (a.dataset.expanded === 'true') {
			collapse(a);
		} else {
			expand(a);
		}
	}

	function bind(root) {
		var scope = root || document;
		var links = scope.querySelectorAll('a.file-link, a[href] > img.post-image');
		// querySelectorAll on parent of img: collect unique anchors
		var seen = [];
		function add(a) {
			if (!a || seen.indexOf(a) !== -1) {
				return;
			}
			if (!isImageAnchor(a)) {
				return;
			}
			if (a.dataset.inlineExpandBound === '1') {
				return;
			}
			a.dataset.inlineExpandBound = '1';
			// Do not open a new window — we expand in place
			a.removeAttribute('target');
			a.addEventListener('click', onClick);
			seen.push(a);
		}

		// Prefer explicit file-link anchors
		var fileLinks = scope.querySelectorAll('a.file-link');
		for (var i = 0; i < fileLinks.length; i++) {
			add(fileLinks[i]);
		}
		// Fallback: any anchor wrapping a post-image
		var imgs = scope.querySelectorAll('img.post-image');
		for (var j = 0; j < imgs.length; j++) {
			var p = imgs[j].parentElement;
			if (p && p.tagName === 'A') {
				add(p);
			}
		}
	}

	function init() {
		bind(document);
		document.addEventListener('new_post', function (e) {
			var post = e.detail || e.target;
			if (post && post.nodeType) {
				bind(post);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
