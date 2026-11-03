/**
 * Renders a minimal inline SVG line chart of [timestamp, value] pairs. The SVG is built from DOM nodes, never an
 * HTML string, as the label names a gauge from an agency's feed.
 */
export function initSparklines(): void {
    const svgNamespace = 'http://www.w3.org/2000/svg';

    document.querySelectorAll<HTMLElement>('[data-sparkline]').forEach((element) => {
        const points: Array<[number, number]> = JSON.parse(element.dataset.sparkline || '[]');
        const label = element.dataset.sparklineLabel || 'River level over the last 48 hours';

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

        const svg = document.createElementNS(svgNamespace, 'svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', label);

        const line = document.createElementNS(svgNamespace, 'path');
        line.setAttribute('d', path);
        line.setAttribute('fill', 'none');
        line.setAttribute('stroke', 'currentColor');
        line.setAttribute('stroke-width', '1.5');
        line.setAttribute('stroke-linejoin', 'round');
        line.setAttribute('stroke-linecap', 'round');

        svg.append(line);
        element.replaceChildren(svg);
    });
}
