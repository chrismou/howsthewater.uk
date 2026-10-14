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

            // Hover has already opened it, so a click on the button must not toggle it shut again.
            menu.querySelector(':scope > summary')?.addEventListener('click', (event) => {
                event.preventDefault();
                menu.setAttribute('open', '');
            });
        }
    });

    menus.forEach((menu) => initRegionTabs(menu, canHover));

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

/**
 * In the wide menu, regions on the left switch the rivers shown on the
 * right. Hovering previews a region; without hover, the first tap previews
 * it and a second tap follows the link to its overview.
 */
function initRegionTabs(menu, canHover) {
    const tabs = Array.from(menu.querySelectorAll('[data-nav-region-tab]'));
    const panels = Array.from(menu.querySelectorAll('[data-nav-region-panel]'));

    const select = (slug) => {
        tabs.forEach((tab) => tab.setAttribute('data-selected', tab.dataset.navRegionTab === slug ? 'true' : 'false'));
        panels.forEach((panel) => panel.toggleAttribute('data-nav-region-hidden', panel.dataset.navRegionPanel !== slug));
    };

    tabs.forEach((tab) => {
        if (canHover) {
            tab.addEventListener('mouseenter', () => select(tab.dataset.navRegionTab));
        }

        tab.addEventListener('focus', () => select(tab.dataset.navRegionTab));

        tab.addEventListener('click', (event) => {
            if (!canHover && tab.dataset.selected !== 'true') {
                event.preventDefault();
                select(tab.dataset.navRegionTab);
            }
        });
    });
}
