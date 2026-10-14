/**
 * Help tooltips open on hover and focus through CSS alone. This adds a
 * click or tap toggle for touch screens, closes an open tooltip when the
 * page is clicked elsewhere or Escape is pressed, and keeps the panel on
 * screen when the button sits near the right edge.
 */
export function initTooltips() {
    const tips = Array.from(document.querySelectorAll('[data-tooltip]'));

    if (tips.length === 0) {
        return;
    }

    const closeAll = (except = null) => {
        tips.forEach((tip) => {
            if (tip !== except) {
                tip.removeAttribute('data-open');
            }
        });
    };

    tips.forEach((tip) => {
        const button = tip.querySelector('[data-tooltip-button]');
        const panel = tip.querySelector('[role="tooltip"]');

        button.addEventListener('click', (event) => {
            event.preventDefault();
            const open = tip.hasAttribute('data-open');
            closeAll();

            if (!open) {
                tip.setAttribute('data-open', '');
            }
        });

        // Flip the panel to end at the button when it would otherwise overflow the viewport.
        const keepOnScreen = () => {
            panel.classList.remove('right-0', 'left-0');
            panel.classList.add('left-0');

            if (tip.getBoundingClientRect().left + panel.offsetWidth > window.innerWidth - 16) {
                panel.classList.replace('left-0', 'right-0');
            }
        };

        tip.addEventListener('mouseenter', keepOnScreen);
        button.addEventListener('focus', keepOnScreen);
        button.addEventListener('click', keepOnScreen);
    });

    document.addEventListener('click', (event) => {
        if (!tips.some((tip) => tip.contains(event.target))) {
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
}
