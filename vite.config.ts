import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.ts'], refresh: true }),
        tailwindcss(),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
    build: {
        rollupOptions: {
            output: {
                // Keep the base map renderer apart from the deck.gl/luma.gl WebGL stack so each caches independently.
                manualChunks(id: string) {
                    if (id.includes('node_modules/maplibre-gl')) return 'maplibre';
                    if (/node_modules\/@(deck|luma|loaders|math|probe)\.gl\//.test(id)) return 'deckgl';
                    return undefined;
                },
            },
        },
    },
    server: { watch: { ignored: ['**/storage/framework/views/**', '**/database/*.sqlite'] } },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.{test,spec}.ts'],
        globals: true,
    },
});
