import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/css/style.css',
                'resources/css/schedule.css',
                'resources/css/landing.css',


                'resources/js/common-scripts.js',
                'resources/js/general-scripts.js',
                'resources/js/scripts.js',
                // 'resources/js/bootstrap.js',
                'resources/js/schedule.js',
                // 'resources/js/dashboard-ajax.js',
                'resources/js/settings-prices.js',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
    ],
});
 