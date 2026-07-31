import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * Конфиг для сборки из окружения без write на корень public_html
 * (vite пишет timestamp рядом с --config).
 * outDir: public/js/vite-build — каталог с правами на запись для www-data.
 */
export default defineConfig({
    plugins: [
        laravel({
            publicDirectory: 'public',
            buildDirectory: 'js/vite-build',
            input: [
                'resources/js/vendor.js',
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/css/style.css',
                'resources/css/schedule.css',
                'resources/css/school-schedule-calendar.css',
                'resources/css/landing.css',
                'resources/css/blog.css',
                'resources/css/user.css',
                'resources/css/admin-list-toolbar.css',
                'resources/css/datatables-columns.css',
                'resources/css/kids-tooltip.css',
                'resources/js/common-scripts.js',
                'resources/js/general-scripts.js',
                'resources/js/scripts.js',
                'resources/js/kids-tooltip.js',
                'resources/js/kids-datatable.js',
                'resources/js/schedule.js',
                'resources/js/trainer-workload.js',
                'resources/js/trainer-salary.js',
                'resources/js/trainer-salary-sheets.js',
                'resources/js/setting-prices-manual-paid-modal.js',
                'resources/js/setting-prices-custom-payments.js',
                'resources/js/settings-prices.js',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
    ],
});
