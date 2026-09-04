export const initializePostMenus = () => {
    document.querySelectorAll('.post-menu').forEach((menu) => {
        if (!(menu instanceof HTMLDetailsElement)) {
            return;
        }

        menu.addEventListener('toggle', () => {
            if (!menu.open || window.matchMedia('(max-width: 680px)').matches) {
                menu.classList.remove('post-menu--align-end');
                return;
            }

            const panel = menu.querySelector(':scope > .post-menu__panel');
            if (!(panel instanceof HTMLElement)) {
                return;
            }

            const viewportWidth = document.documentElement.clientWidth;
            const menuLeft = menu.getBoundingClientRect().left;
            const panelWidth = Math.min(245, Math.max(0, viewportWidth - 24));
            menu.classList.toggle('post-menu--align-end', menuLeft + panelWidth > viewportWidth - 8);
        });
    });
};
