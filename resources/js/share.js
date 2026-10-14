/**
 * The "copy link" button on spot pages, and the reveal for monitors with no
 * recent spills in the overflows table.
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

    document.querySelectorAll('[data-show-quiet]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-quiet-monitors]').forEach((body) => body.removeAttribute('hidden'));
            button.closest('p').remove();
        });
    });
}
