import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // 1) Наш общий бандл — первым
                'resources/js/vendor.js',


                'resources/sass/app.scss',
                'resources/js/app.js', //выпил
                'resources/css/style.css',
                'resources/css/schedule.css',
                'resources/css/landing.css',
                'resources/css/user.css',


                'resources/js/common-scripts.js', //выпил
                'resources/js/general-scripts.js',//выпил

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
 