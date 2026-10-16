import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted from Bunny at build time, with font-display: swap and metric-matched fallbacks so text does
            // not shift when the font arrives. Only the faces on screen first (body text and headings) are preloaded.
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                    preload: [{ weight: 400 }, { weight: 600 }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                bunny('Bricolage Grotesque', {
                    weights: [500, 600, 700],
                    preload: [{ weight: 600 }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                bunny('IBM Plex Mono', {
                    weights: [500],
                    preload: false,
                    fallbacks: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
