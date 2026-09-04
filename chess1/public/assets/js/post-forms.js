export const initializePostForms = () => {
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
};
