/**
 * The header's Rivers, Beaches and Lakes items are plain links to pages
 * listing every region, so they work without JavaScript. This turns each
 * into a menu built from the page's nav-menus JSON, the first time it
 * opens. Narrow screens get a full-width accordion of regions; wide screens
 * get two panes, regions on the left (the page's region selected first)
 * and that region's places on the right.
 *
 * With a mouse, hovering opens a menu and clicking the item follows the
 * link. Without hover, the first tap opens the menu and a second follows
 * it. Keyboard focus opens it too, Escape or a click elsewhere closes it.
 */
const CHEVRON = 'M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z';

export function initNavMenus() {
    const menus = Array.from(document.querySelectorAll('[data-nav-menu]'));

    if (menus.length === 0) {
        return;
    }

    const data = readMenus();
    const current = document.querySelector('[data-nav-current]')?.dataset.navCurrent || null;
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    const isOpen = (menu) => menu.hasAttribute('data-open');

    const close = (menu) => {
        menu.removeAttribute('data-open');
        menu.querySelector('[data-nav-trigger]').setAttribute('aria-expanded', 'false');
        menu.querySelector('[data-nav-panel]')?.setAttribute('hidden', '');
    };

    const open = (menu) => {
        menus.filter((other) => other !== menu).forEach(close);

        if (!menu.querySelector('[data-nav-panel]')) {
            menu.append(buildPanel(menu, data[menu.dataset.navMenu] ?? [], current, canHover));
        }

        menu.setAttribute('data-open', '');
        menu.querySelector('[data-nav-trigger]').setAttribute('aria-expanded', 'true');
        menu.querySelector('[data-nav-panel]').removeAttribute('hidden');
    };

    menus.forEach((menu) => {
        const trigger = menu.querySelector('[data-nav-trigger]');
        trigger.setAttribute('aria-expanded', 'false');

        if (canHover) {
            menu.addEventListener('mouseenter', () => open(menu));
            menu.addEventListener('mouseleave', () => close(menu));
        }

        // Without hover, the first tap opens the menu; a second follows the link to the page of every region.
        trigger.addEventListener('click', (event) => {
            if (!canHover && !isOpen(menu)) {
                event.preventDefault();
                open(menu);
            }
        });

        trigger.addEventListener('focus', () => trigger.matches(':focus-visible') && open(menu));
        menu.addEventListener('focusout', (event) => {
            if (!menu.contains(event.relatedTarget)) {
                close(menu);
            }
        });
    });

    document.addEventListener('click', (event) => {
        menus.filter((menu) => !menu.contains(event.target)).forEach(close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const menu = menus.find(isOpen);

        if (menu) {
            close(menu);
            menu.querySelector('[data-nav-trigger]').focus();
        }
    });
}

/**
 * @returns {Record<string, Array<{slug: string, name: string, url: string, blurb: string, groups: Array<[string, Array<[string, string]>]>}>>}
 */
function readMenus() {
    try {
        return JSON.parse(document.getElementById('nav-menus')?.textContent || '{}');
    } catch {
        return {};
    }
}

/**
 * The menu's panel: an accordion on narrow screens, two panes on wide ones.
 * It starts flush with the item and carries its own top padding, so moving
 * the mouse into it never leaves the menu.
 */
function buildPanel(menu, regions, current, canHover) {
    const selected = regions.some((region) => region.slug === current) ? current : regions[0]?.slug;
    const hub = menu.querySelector('[data-nav-trigger]');
    const hubLabel = `All ${hub.textContent.trim().toLowerCase()}`;

    const panel = element('div', 'absolute inset-x-0 top-full z-[1100] px-4 pt-2 sm:inset-x-auto sm:left-0 sm:w-max sm:max-w-[calc(100vw-2rem)] sm:px-0');
    panel.setAttribute('data-nav-panel', '');
    panel.id = `nav-panel-${menu.dataset.navMenu}`;
    hub.setAttribute('aria-controls', panel.id);

    const box = element('div', 'overflow-hidden rounded-lg border border-line bg-surface text-sm text-ink-2 shadow-lg');
    box.append(narrowPanel(regions, selected, hub.href, hubLabel), widePanel(regions, selected, hub.href, hubLabel));
    panel.append(box);

    initRegionTabs(panel, canHover);

    return panel;
}

function narrowPanel(regions, selected, hubUrl, hubLabel) {
    const list = element('div', 'divide-y divide-line-2 sm:hidden');
    const all = link(hubUrl, 'block px-4 py-3 font-medium text-accent hover:underline', `${hubLabel} →`);

    list.append(all, ...regions.map((region) => {
        const section = element('details', 'group/region');
        section.open = region.slug === selected;

        const summary = element('summary', 'flex cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold text-ink [&::-webkit-details-marker]:hidden', region.name);
        summary.append(chevron('h-4 w-4 text-faint transition group-open/region:rotate-180'));

        const body = element('div', 'flex flex-col gap-3 px-4 pb-4');
        body.append(link(region.url, 'font-medium text-accent hover:underline', `${region.name} overview →`), ...region.groups.map(([heading, items]) => {
            const group = document.createElement('div');
            group.append(element('p', 'mb-1 text-xs font-medium uppercase tracking-wide text-faint', heading), places(items, 'grid grid-cols-2 gap-x-4 gap-y-1'));

            return group;
        }));

        section.append(summary, body);

        return section;
    }));

    return list;
}

/**
 * Groups sit on a three-column grid of a fixed-width pane, so names line up
 * from row to row. A group of more than five places spans all three, split
 * into three lists, or two when every group is that big (so nothing shares
 * the grid) and a name is too long for a third of the pane.
 */
function widePanel(regions, selected, hubUrl, hubLabel) {
    const panes = element('div', 'hidden sm:flex');
    const tabs = element('ul', 'w-48 shrink-0 border-r border-line-2 bg-surface-2 py-2');
    tabs.setAttribute('role', 'list');

    tabs.append(...regions.map((region) => {
        const tab = link(region.url, 'flex w-full items-center justify-between px-4 py-2 font-medium text-ink-2 hover:text-ink data-[selected=true]:bg-surface data-[selected=true]:text-ink', region.name);
        tab.dataset.navRegionTab = region.slug;
        tab.dataset.selected = region.slug === selected ? 'true' : 'false';
        const arrow = element('span', 'text-faint', '›');
        arrow.setAttribute('aria-hidden', 'true');
        tab.append(arrow);
        const item = document.createElement('li');
        item.append(tab);

        return item;
    }));

    const all = document.createElement('li');
    all.append(link(hubUrl, 'mt-1 block border-t border-line-2 px-4 pt-3 pb-1 font-medium text-accent hover:underline', `${hubLabel} →`));
    tabs.append(all);

    const content = element('div', 'w-[34rem] max-w-[calc(100vw-15rem)] p-4');
    const span = (items) => (items.length > 5 ? 3 : 1);

    content.append(...regions.map((region) => {
        const panel = element('div', 'grid grid-flow-row-dense grid-cols-3 gap-x-6 gap-y-4 [&[data-nav-region-hidden]]:hidden');
        panel.dataset.navRegionPanel = region.slug;
        panel.toggleAttribute('data-nav-region-hidden', region.slug !== selected);

        const header = element('div', 'col-span-full border-b border-line-2 pb-3');
        header.append(link(region.url, 'font-semibold text-ink hover:underline', `${region.name} overview`), element('span', 'text-xs text-muted', ` ${region.blurb}`));

        const wideColumns = region.groups.every(([, items]) => span(items) === 3) && region.groups.some(([, items]) => items.some(([name]) => name.length > 18)) ? 2 : 3;

        panel.append(header, ...region.groups.map(([heading, items]) => {
            const group = document.createElement('div');
            group.style.gridColumn = `span ${span(items)}`;
            const list = places(items, 'grid gap-x-6 gap-y-1');
            list.style.gridTemplateColumns = `repeat(${span(items) === 3 ? wideColumns : 1}, minmax(0, 1fr))`;
            group.append(element('p', 'mb-1.5 text-xs font-medium uppercase tracking-wide text-faint', heading), list);

            return group;
        }));

        return panel;
    }));

    panes.append(tabs, content);

    return panes;
}

/**
 * In the wide menu, regions on the left switch the places shown on the
 * right. Hovering previews a region; without hover, the first tap previews
 * it and a second tap follows the link to its overview.
 */
function initRegionTabs(panel, canHover) {
    const tabs = Array.from(panel.querySelectorAll('[data-nav-region-tab]'));
    const panels = Array.from(panel.querySelectorAll('[data-nav-region-panel]'));

    const select = (slug) => {
        tabs.forEach((tab) => tab.setAttribute('data-selected', tab.dataset.navRegionTab === slug ? 'true' : 'false'));
        panels.forEach((pane) => pane.toggleAttribute('data-nav-region-hidden', pane.dataset.navRegionPanel !== slug));
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

function places(items, className) {
    const list = element('ul', className);

    list.append(...items.map(([name, path]) => {
        const item = document.createElement('li');
        item.append(link(path, 'block py-0.5 hover:text-ink', name));

        return item;
    }));

    return list;
}

function chevron(className) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', className);
    svg.setAttribute('viewBox', '0 0 20 20');
    svg.setAttribute('fill', 'currentColor');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('fill-rule', 'evenodd');
    path.setAttribute('clip-rule', 'evenodd');
    path.setAttribute('d', CHEVRON);
    svg.append(path);

    return svg;
}

function link(href, className, text) {
    const anchor = element('a', className, text);
    anchor.href = href;

    return anchor;
}

function element(tag, className, text = null) {
    const node = document.createElement(tag);
    node.className = className;

    if (text !== null) {
        node.textContent = text;
    }

    return node;
}
