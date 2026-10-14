/**
 * Region menus are <details> elements, so they open on click or keyboard
 * without JavaScript. On devices with a pointer that hovers, they also
 * open on hover, and any open menu closes on a click elsewhere or Escape.
 */
export function initNavMenus() {
    const menus = Array.from(document.querySelectorAll('[data-nav-menu]'));

    if (menus.length === 0) {
        return;
    }

    const closeAll = (except = null) => {
        menus.forEach((menu) => {
            if (menu !== except) {
                menu.removeAttribute('open');
            }
        });
    };

    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    menus.forEach((menu) => {
        menu.addEventListener('toggle', () => {
            if (menu.open) {
                closeAll(menu);
            }
        });

        if (canHover) {
            menu.addEventListener('mouseenter', () => menu.setAttribute('open', ''));
            menu.addEventListener('mouseleave', () => menu.removeAttribute('open'));
        }
    });

    document.addEventListener('click', (event) => {
        if (!menus.some((menu) => menu.contains(event.target))) {
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
}
