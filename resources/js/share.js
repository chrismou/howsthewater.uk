/**
 * The share button on place pages, and the button that loads the rest of
 * the overflows table. On a touch screen with a share sheet the button
 * shares the page; elsewhere (most desktops) it copies the link.
 */
export function initShareControls() {
    const canShare = typeof navigator.share === 'function' && window.matchMedia('(pointer: coarse)').matches;

    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        const label = button.querySelector('[data-copy-link-label]');
        const restingLabel = canShare ? 'Share' : 'Copy link';

        if (canShare) {
            label.textContent = restingLabel;
            button.setAttribute('aria-label', `Share ${button.dataset.shareTitle}`);
            button.querySelector('[data-copy-link-icon]').setAttribute('hidden', '');
            button.querySelector('[data-share-icon]').removeAttribute('hidden');
        }

        button.addEventListener('click', async () => {
            if (canShare) {
                try {
                    await navigator.share({ title: document.title, url: button.dataset.copyLink });
                } catch {
                    // Closing the share sheet rejects too; there is nothing to report.
                }

                return;
            }

            try {
                await navigator.clipboard.writeText(button.dataset.copyLink);
                label.textContent = 'Link copied';
            } catch {
                label.textContent = 'Copy failed';
            }

            setTimeout(() => { label.textContent = restingLabel; }, 2000);
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
