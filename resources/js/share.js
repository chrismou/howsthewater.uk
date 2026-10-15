/**
 * The "copy link" button on spot pages, and the button that loads the rest
 * of the overflows table.
 */
export function initShareControls() {
    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        const label = button.querySelector('[data-copy-link-label]');

        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copyLink);
                label.textContent = 'Link copied';
            } catch {
                label.textContent = 'Copy failed';
            }

            setTimeout(() => { label.textContent = 'Copy link'; }, 2000);
        });
    });

    document.querySelectorAll('[data-more-monitors]').forEach((control) => {
        const button = control.querySelector('[data-more-monitors-button]');
        const loading = control.querySelector('[data-more-monitors-loading]');
        const rows = control.closest('section').querySelector('[data-monitor-rows]');

        button.addEventListener('click', async () => {
            button.hidden = true;
            loading.hidden = false;

            try {
                const response = await fetch(control.dataset.moreMonitors, { headers: { Accept: 'text/html' } });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                rows.insertAdjacentHTML('beforeend', await response.text());
                control.remove();
            } catch {
                loading.hidden = true;
                button.hidden = false;
                button.textContent = 'Could not load them, try again';
            }
        });
    });
}
