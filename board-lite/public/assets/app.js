(() => {
    'use strict';

    const passwordKey = 'chessboard.deletionPassword';

    const randomPassword = () => {
        const bytes = new Uint8Array(18);
        crypto.getRandomValues(bytes);
        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    };

    let deletionPassword = null;
    try {
        deletionPassword = localStorage.getItem(passwordKey);
    } catch {
        deletionPassword = null;
    }
    if (!deletionPassword) {
        deletionPassword = randomPassword();
        try {
            localStorage.setItem(passwordKey, deletionPassword);
        } catch {
            // Posting still works when browser storage is disabled.
        }
    }

    document.querySelectorAll('.deletion-password').forEach((input) => {
        if (!input.value) {
            input.value = deletionPassword;
        }
        input.addEventListener('change', () => {
            if (input.value) {
                deletionPassword = input.value;
                try {
                    localStorage.setItem(passwordKey, deletionPassword);
                } catch {
                    // Keep the value for this page even when storage is unavailable.
                }
                document.querySelectorAll('.deletion-password').forEach((other) => {
                    if (other !== input) {
                        other.value = deletionPassword;
                    }
                });
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
