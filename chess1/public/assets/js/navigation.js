export const initializeNavigation = () => {
    document.querySelectorAll('.history-back').forEach((button) => {
        button.addEventListener('click', () => history.back());
    });
};
