import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // Photos from zdjecia/ that Blade sections use through Vite::asset().
            assets: ['zdjecia/*.webp'],
            refresh: true,
            // The site's own files from resources/fonts/web (MA-271): one woff2 per weight with Latin and the Polish letters,
            // instead of a `latin` and a `latin-ext` file from Bunny for each. How they are made: resources/fonts/web/subset.sh.
            // Only the variants the pages render; the panel uses the same ones. Preloaded: the body text and the light headings,
            // which every page shows on the first screen (text paints about 0.3 s sooner on a phone); the rest loads when used.
            fonts: [
                local('Instrument Sans', {
                    variants: [
                        { src: 'resources/fonts/web/InstrumentSans-Regular.woff2', weight: 400 },
                        { src: 'resources/fonts/web/InstrumentSans-Medium.woff2', weight: 500 },
                        { src: 'resources/fonts/web/InstrumentSans-SemiBold.woff2', weight: 600 },
                    ],
                    preload: [{ weight: 400 }],
                    optimizedFallbacks: false,
                }),
                local('Newsreader', {
                    variants: [
                        { src: 'resources/fonts/web/Newsreader-Light.woff2', weight: 300 },
                        { src: 'resources/fonts/web/Newsreader-LightItalic.woff2', weight: 300, style: 'italic' },
                        { src: 'resources/fonts/web/Newsreader-Regular.woff2', weight: 400 },
                        { src: 'resources/fonts/web/Newsreader-Italic.woff2', weight: 400, style: 'italic' },
                    ],
                    preload: [{ weight: 300 }],
                    optimizedFallbacks: false,
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
