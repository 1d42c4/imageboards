

/* gettext-compatible _ function */
function _(s) {
	return (typeof l10n != 'undefined' && typeof l10n[s] != 'undefined') ? l10n[s] : s;
}

function until(timestamp) {
	let difference = timestamp - Date.now() / 1000 | 0;
	switch (true) {
	case (difference < 60):
		return "" + difference + ' ' + _('second(s)');
	case (difference < 3600):
		return "" + Math.round(difference / 60) + ' ' + _('minute(s)');
	case (difference < 86400):
		return "" + Math.round(difference / 3600) + ' ' + _('hour(s)');
	case (difference < 604800):
		return "" + Math.round(difference / 86400) + ' ' + _('day(s)');
	case (difference < 31536000):
		return "" + Math.round(difference / 604800) + ' ' + _('week(s)');
	default:
		return "" + Math.round(difference / 31536000) + ' ' + _('year(s)');
	}
}

function ago(timestamp) {
	let difference = (Date.now() / 1000 | 0) - timestamp;
	switch (true) {
	case (difference < 60):
		return "" + difference + ' ' + _('second(s)');
	case (difference < 3600):
		return "" + Math.round(difference/(60)) + ' ' + _('minute(s)');
	case (difference < 86400):
		return "" + Math.round(difference/(3600)) + ' ' + _('hour(s)');
	case (difference < 604800):
		return "" + Math.round(difference/(86400)) + ' ' + _('day(s)');
	case (difference < 31536000):
		return "" + Math.round(difference/(604800)) + ' ' + _('week(s)');
	default:
		return "" + Math.round(difference/(31536000)) + ' ' + _('year(s)');
	}
}

function alert(a, do_confirm, confirm_ok_action, confirm_cancel_action) {
	let handler = document.createElement('div');
	handler.id = 'alert_handler';
	handler.style.display = 'none';
	handler.style.opacity = '0';
	handler.style.transition = 'opacity 0.25s ease';

	let close = function() {
		handler.style.opacity = '0';
		setTimeout(function() {
			if (handler.parentNode) {
				handler.parentNode.removeChild(handler);
			}
		}, 250);
		return false;
	};

	let bg = document.createElement('div');
	bg.id = 'alert_background';
	handler.appendChild(bg);

	let div = document.createElement('div');
	div.id = 'alert_div';
	handler.appendChild(div);

	let closebtn = document.createElement('a');
	closebtn.id = 'alert_close';
	closebtn.href = '#';
	closebtn.textContent = '\u00d7';
	closebtn.addEventListener('click', function(e) { e.preventDefault(); close(); });
	div.appendChild(closebtn);

	let msg = document.createElement('div');
	msg.id = 'alert_message';
	if (typeof a === 'string') {
		msg.textContent = a;
	} else if (a && a.nodeType) {
		msg.appendChild(a);
	} else {
		msg.textContent = String(a);
	}
	div.appendChild(msg);

	let okbtn = document.createElement('button');
	okbtn.className = 'button alert_button';
	okbtn.textContent = _('OK');
	div.appendChild(okbtn);

	if (do_confirm) {
		confirm_ok_action = (typeof confirm_ok_action !== 'function') ? function(){} : confirm_ok_action;
		confirm_cancel_action = (typeof confirm_cancel_action !== 'function') ? function(){} : confirm_cancel_action;
		okbtn.addEventListener('click', confirm_ok_action);
		let cancelbtn = document.createElement('button');
		cancelbtn.className = 'button alert_button';
		cancelbtn.textContent = _('Cancel');
		cancelbtn.addEventListener('click', function() { confirm_cancel_action(); close(); });
		div.appendChild(cancelbtn);
		bg.addEventListener('click', function() { confirm_cancel_action(); close(); });
		closebtn.addEventListener('click', function() { confirm_cancel_action(); });
	}

	bg.addEventListener('click', close);
	okbtn.addEventListener('click', close);

	document.body.appendChild(handler);
	void handler.offsetWidth;
	handler.style.display = 'block';
	handler.style.opacity = '1';
}

var saved = {};

var selectedstyle = 'Yotsuba B';
var styles = {
	'Yotsuba B' : '',
	'Yotsuba C' : '/stylesheets/yotsuba_b.css',
	'Yotsuba' : '/stylesheets/yotsuba.css',
	'Futaba' : '/stylesheets/futaba.css',
	'Futaba Light' : '/stylesheets/futaba-light.css',
	'Burichan' : '/stylesheets/burichan.css',
	'Dark' : '/stylesheets/dark.css',
	'Tomorrow' : '/stylesheets/tomorrow.css',
	'Terminal' : '/stylesheets/terminal2.css',
	'Green Dark' : '/stylesheets/greendark.css',
	'Miku' : '/stylesheets/miku.css',
	'Pink' : '/stylesheets/pink.css',
	'Photon' : '/stylesheets/photon.css',
	'Notsuba' : '/stylesheets/notsuba.css',
};

if (typeof board_name === 'undefined') {
	var board_name = false;
}

function changeStyle(styleName, link) {
	localStorage.stylesheet = styleName;

	let mainStylesheetElement = document.getElementById('stylesheet');
	if (!mainStylesheetElement) {
		mainStylesheetElement = document.createElement('link');
		mainStylesheetElement.rel = 'stylesheet';
		mainStylesheetElement.type = 'text/css';
		mainStylesheetElement.id = 'stylesheet';
		document.getElementsByTagName('head')[0].appendChild(mainStylesheetElement);
	}

	let style = styles[styleName];
	if (typeof style === 'undefined') {
		return;
	}

	if (style === '' || style === null) {
		mainStylesheetElement.href = '';
		mainStylesheetElement.media = 'none';
		mainStylesheetElement.disabled = true;
	} else {
		mainStylesheetElement.disabled = false;
		mainStylesheetElement.media = 'all';
		mainStylesheetElement.href = style + `?v=${resourceVersion}`;
	}

	selectedstyle = styleName;

	let sel = document.getElementById('style-select-dropdown');
	if (sel && sel.value !== styleName) {
		sel.value = styleName;
	}

	try {
		window.dispatchEvent(new CustomEvent('stylesheet', { detail: styleName }));
	} catch (e) { /* older browsers */ }
}

var resourceVersion = document.currentScript.getAttribute('data-resource-version');

if (localStorage.stylesheet) {
	for (let styleName in styles) {
		if (styleName == localStorage.stylesheet) {
			changeStyle(styleName);
			break;
		}
	}
}

function initStyleChooser() {
	if (document.getElementById('style-select-wrap')) {
		return;
	}

	let wrap = document.createElement('div');
	wrap.id = 'style-select-wrap';
	wrap.setAttribute('role', 'navigation');

	let select = document.createElement('select');
	select.id = 'style-select-dropdown';
	select.title = _('Stylesheet');
	select.setAttribute('aria-label', _('Stylesheet'));

	for (let styleName in styles) {
		if (!Object.prototype.hasOwnProperty.call(styles, styleName)) {
			continue;
		}
		let opt = document.createElement('option');
		opt.value = styleName;
		opt.textContent = styleName;
		if (styleName === selectedstyle) {
			opt.selected = true;
		}
		select.appendChild(opt);
	}

	select.addEventListener('change', function () {
		changeStyle(this.value);
	});

	wrap.appendChild(select);

	let body = document.getElementsByTagName('body')[0];
	if (body.firstChild) {
		body.insertBefore(wrap, body.firstChild);
	} else {
		body.appendChild(wrap);
	}
}

function getCookie(cookie_name) {
	let results = document.cookie.match('(^|;) ?' + cookie_name + '=([^;]*)(;|$)');
	if (results) {
		return decodeURIComponent(results[2]);
	}
	return null;
}

function highlightReply(id) {
	if (typeof window.event != "undefined" && event.which == 2) {
		return true;
	}

	let divs = document.getElementsByTagName('div');
	for (let i = 0; i < divs.length; i++) {
		if (divs[i].className.indexOf('post') != -1) {
			divs[i].className = divs[i].className.replace(/highlighted/, '');
		}
	}
	if (id) {
		let post = document.getElementById('reply_' + id);
		if (post) {
			post.className += ' highlighted';
		}
		window.location.hash = id;
	}
	return true;
}

function doPost(form) {
	if (form.elements['name']) {
		localStorage.name = form.elements['name'].value.replace(/( |^)## .+$/, '');
	}
	if (form.elements['email'] && form.elements['email'].value != 'sage') {
		localStorage.email = form.elements['email'].value;
	}

	saved[document.location] = form.elements['body'].value;
	sessionStorage.body = JSON.stringify(saved);

	return form.elements['body'].value != "" || (form.elements['file'] && form.elements['file'].value != "") || (form.elements.file_url && form.elements['file_url'].value != "");
}

/**
 * Load native captcha into all .captcha rows (JSON from securimage.php).
 */
function load_captcha(provider_get, extra) {
	var run = function () {
		actually_load_captcha(provider_get, extra);
	};
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
}

function actually_load_captcha(provider_get, extra) {
	if (!provider_get) {
		return;
	}
	var sep = provider_get.indexOf('?') === -1 ? '?' : '&';
	var endpoint = provider_get + sep + 'mode=get&extra=' + encodeURIComponent(extra || '');

	fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
		.then(function (r) {
			if (!r.ok) {
				throw new Error('captcha http ' + r.status);
			}
			return r.json();
		})
		.then(function (data) {
			var cells = document.querySelectorAll('tr.captcha td');
			for (var i = 0; i < cells.length; i++) {
				var td = cells[i];
				td.innerHTML = '';
				var wrap = document.createElement('div');
				wrap.className = 'captcha_html';
				if (data.captchahtml) {
					wrap.innerHTML = data.captchahtml;
				} else if (data.image) {
					var img = document.createElement('img');
					img.src = data.image;
					img.alt = 'captcha';
					wrap.appendChild(img);
				}
				var text = document.createElement('input');
				text.className = 'captcha_text';
				text.type = 'text';
				text.name = 'captcha_text';
				text.size = 32;
				text.maxLength = 6;
				text.autocomplete = 'off';
				text.required = true;
				var cookie = document.createElement('input');
				cookie.className = 'captcha_cookie';
				cookie.type = 'hidden';
				cookie.name = 'captcha_cookie';
				cookie.value = data.cookie || '';
				td.appendChild(wrap);
				td.appendChild(document.createElement('br'));
				td.appendChild(text);
				td.appendChild(cookie);
			}
		})
		.catch(function () {
			/* leave noscript markup if present */
		});
}

function citeReply(id, with_link) {
	let textarea = document.getElementById('body');
	if (!textarea) {
		return false;
	}

	if (textarea.selectionStart || textarea.selectionStart == '0') {
		let start = textarea.selectionStart;
		let end = textarea.selectionEnd;
		let before = textarea.value.substring(0, start);
		let after = textarea.value.substring(end, textarea.value.length);
		let insert = '>>' + id + '\n';
		textarea.value = before + insert + after;
		textarea.selectionStart = textarea.selectionEnd = start + insert.length;
	} else {
		textarea.value += '>>' + id + '\n';
	}

	try {
		window.dispatchEvent(new CustomEvent('cite', { detail: { id: id, with_link: with_link } }));
	} catch (e) { /* ignore */ }
	textarea.dispatchEvent(new Event('change', { bubbles: true }));

	return false;
}

function rememberStuff() {
	if (document.forms.post) {
		if (localStorage.name && document.forms.post.elements['name']) {
			document.forms.post.elements['name'].value = localStorage.name;
		}
		if (localStorage.email && document.forms.post.elements['email']) {
			document.forms.post.elements['email'].value = localStorage.email;
		}

		if (window.location.hash.indexOf('q') == 1) {
			citeReply(window.location.hash.substring(2), true);
		}

		if (sessionStorage.body) {
			let savedBodies = JSON.parse(sessionStorage.body);
			if (getCookie('serv')) {
				let successful = JSON.parse(getCookie('serv'));
				for (let url in successful) {
					savedBodies[url] = null;
				}
				sessionStorage.body = JSON.stringify(savedBodies);
				document.cookie = 'serv={};expires=0;path=/;';
			}
			if (savedBodies[document.location]) {
				document.forms.post.body.value = savedBodies[document.location];
			}
		}

		if (localStorage.body) {
			document.forms.post.body.value = localStorage.body;
			localStorage.body = '';
		}
	}
}

function init() {
	initStyleChooser();

	if (window.location.hash.indexOf('q') != 1 && window.location.hash.substring(1))
		highlightReply(window.location.hash.substring(1));
}

onready_callbacks = [];
function onReady(fnc) {
	onready_callbacks.push(fnc);
}

function ready() {
	for (let i = 0; i < onready_callbacks.length; i++) {
		onready_callbacks[i]();
	}
}

var post_date = "%m/%d/%y (%a) %H:%M:%S";
var max_images = 1;

onReady(init);


