/*
 * mobile-style.js — mark mobile vs desktop on <html> (vanilla JS).
 */
(function () {
	var mobile = /iPhone|iPod|iPad|Android|Opera Mini|Blackberry|PlayBook|Windows Phone|Tablet PC|Windows CE|IEMobile/i.test(navigator.userAgent);
	document.documentElement.classList.add(mobile ? 'mobile-style' : 'desktop-style');
	window.device_type = mobile ? 'mobile' : 'desktop';
})();
