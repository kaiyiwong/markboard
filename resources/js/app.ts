import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Markboard` : 'Markboard'),
    // Inertia sends a page name such as "Projects/Index"; this maps it to
    // resources/js/Pages/Projects/Index.vue. Vite splits each page into its own chunk.
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];
        if (!page) {
            throw new Error(`Inertia page not found: resources/js/Pages/${name}.vue`);
        }
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
