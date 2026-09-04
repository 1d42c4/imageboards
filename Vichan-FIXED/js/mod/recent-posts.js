/*
 * recent-posts.js — dismiss/hide controls for mod recent posts (vanilla JS).
 */
(function () {
	function init() {
		if (!localStorage.hiddenrecentposts) {
			localStorage.hiddenrecentposts = '{}';
		}

		var hidden_data = {};
		try {
			hidden_data = JSON.parse(localStorage.hiddenrecentposts) || {};
		} catch (e) {
			hidden_data = {};
		}

		function store() {
			localStorage.hiddenrecentposts = JSON.stringify(hidden_data);
		}

		// Delete old hidden posts (7+ days)
		var cutoff = Math.round(Date.now() / 1000) - 60 * 60 * 24 * 7;
		Object.keys(hidden_data).forEach(function (board) {
			Object.keys(hidden_data[board]).forEach(function (id) {
				if (hidden_data[board][id] < cutoff) {
					delete hidden_data[board][id];
				}
			});
		});
		store();

		document.querySelectorAll('a.eita-link').forEach(function (link) {
			var parts = (link.id || '').split('-');
			var id = parts[2];
			var post_container = link.closest('.post-wrapper') || link.parentNode;
			var board = post_container.getAttribute('data-board');
			if (!board || !id) {
				return;
			}
			if (!hidden_data[board]) {
				hidden_data[board] = {};
			}

			var dismiss = document.createElement('a');
			dismiss.className = 'hide-post-link';
			dismiss.href = '#';
			dismiss.textContent = ' Dismiss ';
			dismiss.addEventListener('click', function (e) {
				e.preventDefault();
				hidden_data[board][id] = Math.round(Date.now() / 1000);
				store();
				var prev = post_container.previousElementSibling;
				if (prev && prev.tagName === 'HR') {
					prev.style.display = 'none';
				}
				Array.prototype.forEach.call(post_container.children, function (ch) {
					ch.style.display = 'none';
				});
			});
			link.parentNode.insertBefore(dismiss, link);

			if (hidden_data[board][id]) {
				dismiss.click();
			}
		});

		var erase = document.getElementById('erase-local-data');
		if (erase) {
			erase.addEventListener('click', function (e) {
				e.preventDefault();
				localStorage.hiddenrecentposts = '{}';
				erase.textContent = 'Loading...';
				location.reload();
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
