/*
 * Collapsible post form — vanilla JS (no jQuery).
 * Usage: $config['additional_javascript'][] = 'js/hide-form.js';
 */
(function () {
	function init() {
		if (typeof active_page === 'undefined' || (active_page !== 'index' && active_page !== 'thread')) {
			return;
		}
		var form_el = document.querySelector('form[name="post"]');
		if (!form_el) {
			return;
		}
		var form_msg = active_page === 'index' ? 'Start a New Thread' : 'Post a Reply';
		if (typeof _ === 'function') {
			form_msg = _(form_msg);
		}

		form_el.style.display = 'none';

		var toggle = document.createElement('div');
		toggle.id = 'show-post-form';
		toggle.style.cssText = 'font-size:175%;text-align:center;font-weight:bold';
		var a = document.createElement('a');
		a.href = '#';
		a.style.textDecoration = 'none';
		a.textContent = form_msg;
		toggle.appendChild(document.createTextNode('['));
		toggle.appendChild(a);
		toggle.appendChild(document.createTextNode(']'));

		form_el.parentNode.insertBefore(toggle, form_el.nextSibling);

		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			toggle.style.display = 'none';
			form_el.style.display = '';
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
