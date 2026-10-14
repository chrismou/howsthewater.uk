/**
 * Light and dark are the only themes, light is the default, and the choice
 * is remembered in this browser. The <head> applies the stored choice before
 * paint; this wires up the toggle buttons.
 */
export function initThemeToggle() {
    const root = document.documentElement;

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const dark = root.classList.toggle('dark');

            try {
                localStorage.setItem('theme', dark ? 'dark' : 'light');
            } catch {
                // Storage can be unavailable in private windows; the toggle still works for this page.
            }
        });
    });
}
