/*
 * mod_snippets.js — mod-only helpers (vanilla JS).
 */
function populateForm(frm, data) {
	if (!frm || !data) {
		return;
	}
	Object.keys(data).forEach(function (key) {
		var el = frm.querySelector('[name="' + key + '"]');
		if (el) {
			el.value = data[key];
		}
	});
}
// Legacy jQuery-era name
function populateFormJQuery(frm, data) {
	populateForm(frm, data);
}
