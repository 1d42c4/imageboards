'use strict';
(() => {
  const root = document.querySelector('meta[name="app-root"]').content;
  const url = path => root + path;
  const select = document.querySelector('#theme-select');
  const applyTheme = value => {
    if (![...select.options].some(option => option.value === value)) return;
    document.querySelector('#stylesheet').href = url('stylesheets/' + encodeURIComponent(value) + '.css');
    document.body.dataset.stylesheet = value;
    select.value = value;
  };
  try { applyTheme(localStorage.getItem('vichan-theme') || select.value); } catch { /* Storage is optional. */ }
  select.addEventListener('change', () => { applyTheme(select.value); try { localStorage.setItem('vichan-theme', select.value); } catch {} });
  const message = text => { document.querySelector('#live-message').textContent = text; };
  const refreshCaptcha = () => {
    document.querySelectorAll('.captcha-image').forEach(image => { image.src = url('captcha.php?t=' + crypto.randomUUID()); });
    const input = document.querySelector('[name="captcha"]');
    if (input) input.value = '';
  };
  let staff = document.body.classList.contains('is-moderator');
  const showStaff = () => document.querySelectorAll('.staff-controls').forEach(control => { control.hidden = !staff; });
  const ready = fetch(url('session.php'), {cache: 'no-store', credentials: 'same-origin'})
    .then(async response => { if (!response.ok) throw new Error('Could not initialize the form. Reload this page.'); return response.json(); })
    .then(data => {
      document.querySelectorAll('input[name="csrf"]').forEach(input => { input.value = data.csrf; });
      staff = data.staff;
      showStaff();
      if (document.querySelector('[data-captcha]')) refreshCaptcha();
      return data;
    }).catch(error => { message(error.message); return null; });
  document.querySelectorAll('.refresh-captcha').forEach(button => button.addEventListener('click', refreshCaptcha));
  document.querySelectorAll('form[data-ajax]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const buttons = [...form.querySelectorAll('button[type="submit"],button:not([type])')];
      const status = form.querySelector('.form-status');
      try {
        const session = await ready;
        if (!session) throw new Error('Could not initialize the form. Reload this page.');
        if (form.dataset.busy === '1') return;
        form.dataset.busy = '1';
        buttons.forEach(button => { button.disabled = true; });
        status.textContent = 'Submitting…';
        const data = new FormData(form);
        data.set('csrf', session.csrf);
        data.set('json_response', '1');
        const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'});
        let result;
        try { result = await response.json(); } catch { throw new Error('The server rejected the request. The upload may exceed its size limit.'); }
        if (!response.ok || result.error) throw new Error(result.error || 'The request failed.');
        if (data.get('action') === 'report') { status.textContent = result.message; return; }
        const destination = new URL(result.redirect, location.origin);
        if (destination.origin !== location.origin || !destination.pathname.startsWith(root)) throw new Error('Invalid server redirect.');
        if (destination.pathname === location.pathname && destination.search === location.search) {
          history.replaceState(null, '', destination.href);
          location.reload();
        } else {
          location.assign(destination.href);
        }
      } catch (error) {
        status.textContent = error.message;
        if (form.dataset.ajax === 'post') refreshCaptcha();
      } finally {
        form.dataset.busy = '0';
        buttons.forEach(button => { button.disabled = false; });
      }
    });
  });
  const postForm = document.querySelector('.post-form');
  let restoreForm = null;
  function quickReply(post) {
    if (!postForm) return;
    const thread = post.closest('.thread');
    const originalThread = postForm.elements.thread.value;
    if (thread) postForm.elements.thread.value = thread.id.replace('thread_', '');
    if (!restoreForm && typeof HTMLDialogElement !== 'undefined') {
      const placeholder = document.createComment('posting form');
      postForm.before(placeholder);
      const dialog = document.createElement('dialog');
      dialog.id = 'quick-reply';
      const bar = document.createElement('div');
      bar.className = 'quick-title';
      const heading = document.createElement('strong');
      heading.textContent = 'Quick reply';
      const close = document.createElement('button');
      close.type = 'button'; close.textContent = 'Close';
      close.addEventListener('click', () => dialog.close());
      bar.append(heading, close); dialog.append(bar, postForm); document.body.append(dialog);
      postForm.querySelectorAll('button[type="submit"]').forEach(button => { button.dataset.originalText = button.textContent; button.textContent = 'Reply'; });
      restoreForm = () => {
        placeholder.replaceWith(postForm); postForm.elements.thread.value = originalThread;
        postForm.querySelectorAll('[data-original-text]').forEach(button => { button.textContent = button.dataset.originalText; });
        dialog.remove(); restoreForm = null;
      };
      dialog.addEventListener('close', restoreForm, {once: true});
      dialog.showModal();
    }
    const body = postForm.elements.body;
    body.setRangeText('>>' + post.dataset.postId + '\n', body.selectionStart, body.selectionEnd, 'end');
    body.focus();
  }
  document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    const cite = target.closest('[data-cite]');
    if (cite && postForm && !event.ctrlKey && !event.metaKey) { event.preventDefault(); quickReply(cite.closest('[data-post-id]')); }
    const expand = target.closest('.image-expand');
    if (expand && !event.ctrlKey && !event.metaKey) {
      event.preventDefault(); const image = expand.querySelector('img');
      if (!image.dataset.thumb) image.dataset.thumb = image.src;
      const expanded = image.classList.toggle('expanded');
      image.src = expanded ? image.dataset.original : image.dataset.thumb;
    }
    const wrap = target.closest('[data-wrap]');
    if (wrap && postForm) {
      const textarea = postForm.elements.body;
      const open = wrap.dataset.wrap, close = open === '[spoiler]' ? '[/spoiler]' : open;
      const selected = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
      textarea.setRangeText(open + selected + close, textarea.selectionStart, textarea.selectionEnd, 'select');
      textarea.focus();
    }
    const confirm = target.closest('[data-confirm]');
    if (confirm && !window.confirm(confirm.dataset.confirm)) event.preventDefault();
  });
  document.addEventListener('change', event => {
    if (event.target.matches('.select-post')) {
      const input = document.querySelector('.post-action [name="post_id"]');
      if (input) input.value = event.target.value;
      document.querySelectorAll('.select-post').forEach(check => { if (check !== event.target) check.checked = false; });
    }
  });
  let previews = [];
  document.querySelector('#upload-file')?.addEventListener('change', event => {
    const area = document.querySelector('#upload-preview');
    previews.forEach(objectUrl => URL.revokeObjectURL(objectUrl)); previews = []; area.replaceChildren();
    for (const file of [...event.target.files].slice(0, 4)) {
      const label = document.createElement('span'); label.textContent = file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)';
      area.append(label);
    }
  });
  let refreshBusy = false;
  setInterval(async () => {
    if (!document.querySelector('#auto-refresh')?.checked || document.hidden || refreshBusy || restoreForm) return;
    refreshBusy = true;
    try {
      const response = await fetch(location.pathname + '?refresh=' + Date.now(), {cache: 'no-store'});
      if (!response.ok) return;
      const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
      const incoming = parsed.querySelector('#threads');
      const current = document.querySelector('#threads');
      if (incoming && current && incoming.innerHTML !== current.innerHTML) { current.replaceWith(document.importNode(incoming, true)); showStaff(); message('Thread updated.'); }
    } catch {} finally { refreshBusy = false; }
  }, 30000);
})();
