'use strict';

const Main = {
    quote(id) {
        const comment = document.querySelector('textarea[name="comment"]');
        if (!comment) {
            return;
        }

        const quote = `>>${id}\n`;
        const start = comment.selectionStart ?? comment.value.length;
        const end = comment.selectionEnd ?? start;
        comment.setRangeText(quote, start, end, 'end');
        comment.focus();
    },
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('input:not([type="hidden"])')?.focus();
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-quote]');
        if (button) {
            Main.quote(button.dataset.quote);
        }
    });
});
