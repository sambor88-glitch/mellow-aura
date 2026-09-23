import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

/**
 * laravel-vite-plugin 3.2 writes a woff @font-face right after the woff2 one, with the same family,
 * weight and unicode-range. The later rule wins in CSS, so every visit downloaded the bigger woff
 * file — 176 kB of fonts on the home page instead of about 125 kB. Until the plugin stops emitting
 * woff, the rules go out of the generated stylesheet. Every browser we support reads woff2, and the
 * files stay in the build, so a preload link or the manifest still points at something real.
 */
function woff2Only() {
    return {
        name: 'mellowaura:woff2-only',
        enforce: 'post',
        generateBundle(options, bundle) {
            for (const asset of Object.values(bundle)) {
                if (asset.type !== 'asset' || ! asset.fileName.endsWith('.css')) {
                    continue;
                }

                const css = asset.source.toString();

                if (css.includes('format("woff")')) {
                    asset.source = css.replace(/@font-face \{[^}]*format\("woff"\)[^}]*\}\n*/g, '');
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // Photos from zdjecia/ that Blade sections use through Vite::asset().
            assets: ['zdjecia/*.webp'],
            refresh: true,
            fonts: [
                // latin-ext carries ą ę ł ś ż ź ć ń; without it Polish letters fell back to a system font mid-word.
                // No preload: that would pull every weight of both families at once, also the ones a page never uses.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
                bunny('Newsreader', {
                    weights: [300, 400],
                    styles: ['normal', 'italic'],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
        woff2Only(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
