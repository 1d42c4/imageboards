export const initializeModeratorForms = () => {
    document.querySelectorAll('.moderator-edit-form').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const fileInput = form.querySelector('input[type="file"][name="image"]');
        const removeInput = form.querySelector('input[type="checkbox"][name="remove_image"]');
        if (!(fileInput instanceof HTMLInputElement) || !(removeInput instanceof HTMLInputElement)) {
            return;
        }

        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files.length > 0) {
                removeInput.checked = false;
            }
        });

        removeInput.addEventListener('change', () => {
            if (removeInput.checked) {
                fileInput.value = '';
            }
        });
    });
};
