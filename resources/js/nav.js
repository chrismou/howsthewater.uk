/**
 * Region menus are <details> elements, so they open on click or keyboard
 * without JavaScript. On devices with a pointer that hovers, they also
 * open on hover, and any open menu closes on a click elsewhere or Escape.
 * The page carries only each region's overview link; the places are listed
 * from the page's nav-menus JSON the first time a menu opens.
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

    const places = readPlaces();

    menus.forEach((menu) => {
        menu.addEventListener('toggle', () => {
            if (menu.open) {
                listPlaces(menu, places[menu.dataset.navMenu] ?? {});
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

/**
 * @returns {Record<string, Record<string, Array<[string, Array<[string, string]>]>>>}
 */
function readPlaces() {
    try {
        return JSON.parse(document.getElementById('nav-menus')?.textContent || '{}');
    } catch {
        return {};
    }
}

/**
 * Fills each region's place lists, once per menu. Narrow screens list each
 * group in two columns. Wide screens lay groups on a three-column grid: a
 * group of more than five places spans all three, split into three lists,
 * or two when every group is that big and a name would not fit a third.
 */
function listPlaces(menu, regions) {
    if (menu.hasAttribute('data-nav-listed')) {
        return;
    }

    menu.setAttribute('data-nav-listed', '');

    menu.querySelectorAll('[data-nav-groups]').forEach((container) => {
        const groups = regions[container.dataset.navGroups] ?? [];
        const wide = container.dataset.navLayout === 'wide';
        const span = (items) => (items.length > 5 ? 3 : 1);
        const wideColumns = groups.every(([, items]) => span(items) === 3) && groups.some(([, items]) => items.some(([name]) => name.length > 18)) ? 2 : 3;

        container.replaceChildren(...groups.map(([heading, items]) => {
            const group = document.createElement('div');
            const title = element('p', wide ? 'mb-1.5 text-xs font-medium uppercase tracking-wide text-faint' : 'mb-1 text-xs font-medium uppercase tracking-wide text-faint', heading);
            const list = element('ul', wide ? 'grid gap-x-6 gap-y-1' : 'grid grid-cols-2 gap-x-4 gap-y-1');

            if (wide) {
                group.style.gridColumn = `span ${span(items)}`;
                list.style.gridTemplateColumns = `repeat(${span(items) === 3 ? wideColumns : 1}, minmax(0, 1fr))`;
            }

            list.replaceChildren(...items.map(([name, path]) => {
                const link = element('a', 'block py-0.5 hover:text-ink', name);
                link.href = path;
                const item = document.createElement('li');
                item.append(link);

                return item;
            }));

            group.append(title, list);

            return group;
        }));
    });
}

function element(tag, className, text = null) {
    const node = document.createElement(tag);
    node.className = className;

    if (text !== null) {
        node.textContent = text;
    }

    return node;
}
