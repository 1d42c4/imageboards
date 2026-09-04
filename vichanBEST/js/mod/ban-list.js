/*
 * ban-list.js — vanilla ban list (no jQuery / longtable).
 */
function banlist_init(token, my_boards, publicMode) {
	// publicMode true when third arg set (public banlist theme); false/undefined for mod panel
	var inMod = !publicMode;
	var url = inMod ? ('?/bans.json/' + token) : token;
	var table = document.getElementById('banlist');
	var selected = {};

	if (!table) {
		return;
	}

	function esc(s) {
		if (s === null || s === undefined) {
			return '';
		}
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function now() {
		return (Date.now() / 1000) | 0;
	}

	function fmtAgo(ts) {
		if (typeof ago === 'function') {
			return ago(ts) + (typeof _ === 'function' ? _(' ago') : ' ago');
		}
		return new Date(ts * 1000).toLocaleString();
	}

	function fmtUntil(ts) {
		if (typeof until === 'function') {
			return until(ts);
		}
		return new Date(ts * 1000).toLocaleString();
	}

	function render(rows) {
		var thead = '<tr class="row">' +
			(inMod ? '<th><input type="checkbox" id="select-all" title="Select all"></th>' : '') +
			'<th>' + (typeof _ === 'function' ? _('IP address') : 'IP address') + '</th>' +
			'<th>' + (typeof _ === 'function' ? _('Reason') : 'Reason') + '</th>' +
			'<th>' + (typeof _ === 'function' ? _('Board') : 'Board') + '</th>' +
			'<th>' + (typeof _ === 'function' ? _('Set') : 'Set') + '</th>' +
			'<th>' + (typeof _ === 'function' ? _('Expires') : 'Expires') + '</th>' +
			'<th>' + (typeof _ === 'function' ? _('Staff') : 'Staff') + '</th>' +
			(inMod ? '<th>' + (typeof _ === 'function' ? _('Edit') : 'Edit') + '</th>' : '') +
			'</tr>';

		var body = rows.map(function (f) {
			var expired = f.expires && f.expires !== 0 && f.expires < now();
			var trClass = expired ? ' style="text-decoration:line-through"' : '';
			var pre = '';
			if (inMod && f.access) {
				pre = '<input type="checkbox" class="unban" data-id="' + esc(f.id) + '"' +
					(selected[f.id] ? ' checked' : '') + '>';
			}
			var mask = esc(f.mask || '');
			var maskCell = mask;
			if (inMod && f.single_addr && !f.masked && f.mask) {
				maskCell = '<a href="?/IP/' + encodeURIComponent(f.mask) + '">' + mask + '</a>';
			}
			var reason = f.reason ? f.reason : '-';
			var board = f.board ? ('/' + esc(f.board) + '/') : '<em>' + (typeof _ === 'function' ? _('all') : 'all') + '</em>';
			var created = fmtAgo(f.created | 0);
			var expires;
			if (!f.expires || f.expires === 0) {
				expires = '<em>' + (typeof _ === 'function' ? _('never') : 'never') + '</em>';
			} else {
				expires = new Date((f.expires | 0) * 1000).toLocaleString();
				if (f.expires >= now()) {
					expires += ' <small>' + (typeof _ === 'function' ? _('in ') : 'in ') + fmtUntil(f.expires | 0) + '</small>';
				}
			}
			var staff = f.username ? esc(f.username) : '<em>' + (typeof _ === 'function' ? _('system') : 'system') + '</em>';
			if (inMod && f.username && f.username !== '?' && !f.vstaff) {
				staff = '<a href="?/new_PM/' + encodeURIComponent(f.username) + '">' + esc(f.username) + '</a>';
			}
			var edit = inMod ? ('<a href="?/edit_ban/' + esc(f.id) + '">Edit</a>') : '';

			return '<tr class="row"' + trClass + '>' +
				(inMod ? '<td>' + pre + '</td>' : '') +
				'<td>' + maskCell + '</td>' +
				'<td>' + reason + '</td>' +
				'<td>' + board + '</td>' +
				'<td>' + created + '</td>' +
				'<td>' + expires + '</td>' +
				'<td>' + staff + '</td>' +
				(inMod ? '<td>' + edit + '</td>' : '') +
				'</tr>';
		}).join('');

		table.innerHTML = thead + body;

		var selAll = document.getElementById('select-all');
		if (selAll) {
			selAll.addEventListener('change', function () {
				var on = selAll.checked;
				table.querySelectorAll('input.unban').forEach(function (cb) {
					cb.checked = on;
					selected[cb.getAttribute('data-id')] = on;
				});
			});
		}
		table.querySelectorAll('input.unban').forEach(function (cb) {
			cb.addEventListener('change', function () {
				selected[cb.getAttribute('data-id')] = cb.checked;
			});
		});
	}

	function applyFilter(data) {
		var onlyMine = document.getElementById('only_mine');
		var onlyActive = document.getElementById('only_not_expired');
		var search = document.getElementById('search');
		var terms = search && search.value ? search.value.trim().split(/\s+/) : [];

		return data.filter(function (e) {
			if (onlyMine && onlyMine.checked && my_boards && my_boards.indexOf(e.board) === -1 && e.board) {
				return false;
			}
			if (onlyActive && onlyActive.checked && e.expires && e.expires !== 0 && e.expires < now()) {
				return false;
			}
			if (!terms.length) {
				return true;
			}
			var blob = [e.mask, e.reason, e.board, e.username, e.message].join(' ').toLowerCase();
			return terms.every(function (t) {
				return blob.indexOf(t.toLowerCase()) !== -1;
			});
		});
	}

	var allData = [];

	function refresh() {
		render(applyFilter(allData));
	}

	fetch(url, { credentials: 'same-origin' })
		.then(function (r) {
			if (!r.ok) {
				throw new Error('ban list HTTP ' + r.status);
			}
			return r.json();
		})
		.then(function (t) {
			allData = Array.isArray(t) ? t : [];
			refresh();
		})
		.catch(function (err) {
			table.innerHTML = '<tr><td>' + esc(err.message || String(err)) + '</td></tr>';
		});

	['only_mine', 'only_not_expired', 'search'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) {
			el.addEventListener(id === 'search' ? 'input' : 'change', refresh);
		}
	});

	var unbanBtn = document.getElementById('unban');
	var form = document.querySelector('.banform');
	if (unbanBtn && form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
		});
		unbanBtn.addEventListener('click', function () {
			if (!confirm('Are you sure you want to unban the selected IPs?')) {
				return;
			}
			form.querySelectorAll('.hiddens').forEach(function (n) {
				n.remove();
			});
			var h = document.createElement('input');
			h.type = 'hidden';
			h.name = 'unban';
			h.value = 'unban';
			h.className = 'hiddens';
			form.appendChild(h);
			Object.keys(selected).forEach(function (id) {
				if (!selected[id]) {
					return;
				}
				var i = document.createElement('input');
				i.type = 'hidden';
				i.name = 'ban_' + id;
				i.value = 'unban';
				i.className = 'hiddens';
				form.appendChild(i);
			});
			form.submit();
		});
	}
}
