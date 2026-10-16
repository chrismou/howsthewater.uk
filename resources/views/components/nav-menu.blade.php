{{--
    A top-level menu (Rivers, Beaches, Lakes). The page carries only a link to that kind of place's page (/rivers and so
    on), which lists every region, so the menu still leads somewhere without JavaScript. nav.js turns the link into a
    menu: regions and their places come from the page's nav-menus JSON (NavigationMenus::menusJson()), built into the
    panel the first time it opens. menu: the key into that JSON. url: the page to link to.
--}}
@props(['label', 'menu', 'url'])

<div class="sm:relative" data-nav-menu="{{ $menu }}">
    <a href="{{ $url }}" data-nav-trigger class="flex items-center gap-1 text-sm font-medium text-ink">
        {{ $label }}
        <svg class="h-4 w-4 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
        </svg>
    </a>
</div>
