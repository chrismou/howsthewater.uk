/**
 * On phones the header's search box is hidden behind a button, so the menus
 * and the page start higher. The button is a link to the search page, which
 * has its own box; with JavaScript it opens the header's box in place.
 */
export function initSearchToggle() {
    const toggle = document.querySelector('[data-search-toggle]');
    const form = document.querySelector('[data-search-form]');

    if (!toggle || !form) {
        return;
    }

    toggle.addEventListener('click', (event) => {
        event.preventDefault();

        const opening = form.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');

        if (opening) {
            form.querySelector('input')?.focus();
        }
    });
}
