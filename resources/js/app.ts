// Design system first, in the order its setup guide requires; project styles last.
import '../../design-system/fonts.css';
import '../../design-system/colors.css';
import '../../design-system/tokens.css';
import '../../design-system/palettes.css';
import '../../design-system/character.css';
import '../../design-system/base.css';
import '../css/app.css';

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
