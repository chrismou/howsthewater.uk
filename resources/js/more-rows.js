/**
 * "Show more" under the spills pages' long lists: each click fetches the next batch of rows after those shown (the
 * list's own URL with ?offset=) and appends them to the list's [data-more-rows-body], until every row is shown.
 */
export function initMoreRows() {
    document.querySelectorAll('[data-more-rows]').forEach((control) => {
        const button = control.querySelector('[data-more-rows-button]');
        const loading = control.querySelector('[data-more-rows-loading]');
        const shownLabel = control.querySelector('[data-more-rows-shown]');
        const body = control.closest('[data-more-rows-list]').querySelector('[data-more-rows-body]');
        const total = Number(control.dataset.total);
        const batch = Number(control.dataset.batch);
        let shown = Number(control.dataset.shown);

        button.addEventListener('click', async () => {
            button.hidden = true;
            loading.hidden = false;

            try {
                const url = new URL(control.dataset.moreRows, window.location.href);
                url.searchParams.set('offset', String(shown));
                const response = await fetch(url, { headers: { Accept: 'text/html' } });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const before = body.rows.length;
                body.insertAdjacentHTML('beforeend', await response.text());
                const added = body.rows.length - before;
                shown += added;

                // Nothing more to load, or the list shrank since the page was built.
                if (shown >= total || added === 0) {
                    control.remove();

                    return;
                }

                const remaining = total - shown;
                shownLabel.textContent = shown.toLocaleString('en-GB');
                button.textContent = remaining > batch ? `Show ${batch} more` : `Show the other ${remaining.toLocaleString('en-GB')}`;
                loading.hidden = true;
                button.hidden = false;
            } catch {
                loading.hidden = true;
                button.hidden = false;
                button.textContent = 'Could not load them, try again';
            }
        });
    });
}
