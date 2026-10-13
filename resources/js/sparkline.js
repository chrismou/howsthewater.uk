/**
 * Renders a minimal inline SVG line chart of [timestamp, value] pairs.
 */
export function initSparklines() {
    document.querySelectorAll('[data-sparkline]').forEach((element) => {
        const points = JSON.parse(element.dataset.sparkline || '[]');

        if (points.length < 2) {
            return;
        }

        const width = element.clientWidth || 300;
        const height = element.clientHeight || 96;
        const padding = 4;

        const xs = points.map((point) => point[0]);
        const ys = points.map((point) => point[1]);
        const minX = Math.min(...xs);
        const maxX = Math.max(...xs);
        const minY = Math.min(...ys);
        const maxY = Math.max(...ys);
        const spanX = maxX - minX || 1;
        const spanY = maxY - minY || 0.1;

        const path = points
            .map(([x, y], index) => {
                const px = padding + ((x - minX) / spanX) * (width - padding * 2);
                const py = height - padding - ((y - minY) / spanY) * (height - padding * 2);
                return `${index === 0 ? 'M' : 'L'}${px.toFixed(1)},${py.toFixed(1)}`;
            })
            .join(' ');

        element.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" width="100%" height="100%" role="img" aria-label="River level over the last 48 hours">
                <path d="${path}" fill="none" stroke="#0284c7" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
            </svg>`;
    });
}
