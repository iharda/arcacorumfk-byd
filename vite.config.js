import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/kapi.js',
                // Panel temaları: Filament'in hazır CSS'i önceden derlenmiş bir
                // pakettir ve `app.css` panele hiç girmez. Tema olmadan panel
                // görünümlerindeki kendi sınıflarımız derlenmez -- satır içi
                // stil mecburiyetinin sebebi buydu.
                'resources/css/filament/uye/theme.css',
                'resources/css/filament/kurum/theme.css',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
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
