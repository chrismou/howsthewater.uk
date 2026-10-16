/**
 * Help buttons ("?") show their explanation in one shared panel: on hover
 * with a mouse, on keyboard focus, and on tap for touch screens. The text
 * comes from the hidden glossary at the end of the page. The panel is
 * fixed to the viewport and placed under the button (above it when there
 * is no room below), shifted sideways so it never leaves the screen.
 */
const EDGE = 16;
const GAP = 8;

export function initTooltips() {
    const panel = document.querySelector('[data-help-panel]');
    const buttons = Array.from(document.querySelectorAll('[data-help]'));

    if (!panel || buttons.length === 0) {
        return;
    }

    // The open button, and whether a click or tap opened it (then it stays open until dismissed, not on mouse out).
    let current = null;
    let pinned = false;

    const place = () => {
        if (!current) {
            return;
        }

        const button = current.getBoundingClientRect();
        const width = panel.offsetWidth;
        const height = panel.offsetHeight;
        const left = Math.min(Math.max(button.left, EDGE), window.innerWidth - EDGE - width);
        const below = button.bottom + GAP;
        const top = below + height > window.innerHeight - EDGE && button.top - GAP - height >= EDGE ? button.top - GAP - height : below;

        panel.style.transform = `translate(${Math.max(left, EDGE)}px, ${top}px)`;
    };

    const show = (button, pin = false) => {
        const text = document.getElementById(button.getAttribute('aria-describedby'));

        if (!text) {
            return;
        }

        current?.removeAttribute('data-open');
        current = button;
        pinned = pin;
        button.setAttribute('data-open', '');
        panel.textContent = text.textContent.trim();
        panel.hidden = false;
        place();
    };

    const hide = () => {
        current?.removeAttribute('data-open');
        current = null;
        pinned = false;
        panel.hidden = true;
    };

    buttons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            current === button && pinned ? hide() : show(button, true);
        });
        // Keyboard focus only: a tap also focuses the button, and its click handles that.
        button.addEventListener('focus', () => button.matches(':focus-visible') && show(button));
        button.addEventListener('blur', () => current === button && !pinned && hide());
        button.addEventListener('pointerenter', (event) => event.pointerType === 'mouse' && current !== button && show(button));
        button.addEventListener('pointerleave', (event) => event.pointerType === 'mouse' && current === button && !pinned && hide());
    });

    document.addEventListener('click', (event) => {
        if (current && !current.contains(event.target)) {
            hide();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });

    window.addEventListener('scroll', place, { passive: true });
    window.addEventListener('resize', place);
}
