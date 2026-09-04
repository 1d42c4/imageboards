(() => {
    'use strict';

    const styleKey = 'chessboard.style';
    const allowedStyles = new Set(['classic', 'clean', 'blue', 'dark']);
    const styleSelect = document.querySelector('#style-switcher');

    let selectedStyle = 'classic';
    try {
        const storedStyle = localStorage.getItem(styleKey);
        if (storedStyle && allowedStyles.has(storedStyle)) {
            selectedStyle = storedStyle;
        }
    } catch {
        // The default style remains available when browser storage is disabled.
    }

    document.documentElement.dataset.style = selectedStyle;
    if (styleSelect instanceof HTMLSelectElement) {
        styleSelect.value = selectedStyle;
        styleSelect.addEventListener('change', () => {
            const nextStyle = allowedStyles.has(styleSelect.value) ? styleSelect.value : 'classic';
            document.documentElement.dataset.style = nextStyle;
            try {
                localStorage.setItem(styleKey, nextStyle);
            } catch {
                // The selection still applies for the current page.
            }
        });
    }

    document.querySelectorAll('[data-post-form-toggle]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        const targetId = button.getAttribute('aria-controls');
        const panel = targetId ? document.getElementById(targetId) : null;
        if (!(panel instanceof HTMLElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const opening = panel.hidden;
            panel.hidden = !opening;
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
            button.textContent = opening ? 'Close' : 'New';
            if (opening) {
                panel.querySelector('textarea, input')?.focus();
            }
        });
    });

    document.querySelectorAll('.quote-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            const textarea = document.querySelector('#reply-body');
            const quote = link.dataset.quote;
            if (!(textarea instanceof HTMLTextAreaElement) || !quote) {
                return;
            }

            event.preventDefault();
            const prefix = textarea.value !== '' && !textarea.value.endsWith('\n') ? '\n' : '';
            textarea.value += `${prefix}${quote}\n`;
            document.querySelector('#reply')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            textarea.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('.history-back').forEach((button) => {
        button.addEventListener('click', () => history.back());
    });
})();
