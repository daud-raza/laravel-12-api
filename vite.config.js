import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/chat.js',
                'Modules/TaskManager/resources/assets/css/tasks.css',
                'Modules/TaskManager/resources/assets/js/tasks.js',
            ],
            refresh: true,
        }),
    ],
});
