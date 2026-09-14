import { syncCsrfToken } from './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';

router.on('navigate', (event) => {
    syncCsrfToken(event.detail.page.props.csrf_token);
});

createInertiaApp({
    title: (title) => (title ? `${title} — LYDO` : 'The Local Youth Development Office of Nabua'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const ziggy = { ...(props.initialPage.props.ziggy ?? {}), absolute: false };
        delete ziggy.location;
        globalThis.Ziggy = ziggy;
        syncCsrfToken(props.initialPage.props.csrf_token);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggy)
            .component('Link', Link)
            .component('Head', Head)
            .mount(el);
    },
    progress: {
        color: '#002D62',
        showSpinner: false,
    },
});
