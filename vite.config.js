import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted: downloaded from Google Fonts at build time, never requested from Google by visitors, with
            // font-display: swap. Google serves one woff2 per face; Bunny also lists woff, which this plugin version
            // emits as a separate rule that wins over the woff2. Only the faces on screen first are preloaded.
            fonts: [
                google('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                    preload: [{ weight: 400 }, { weight: 600 }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                google('Bricolage Grotesque', {
                    weights: [500, 600, 700],
                    preload: [{ weight: 600 }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                google('IBM Plex Mono', {
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
