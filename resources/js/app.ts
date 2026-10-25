import '../css/app.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';

const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

async function resolvePage(name: string): Promise<DefineComponent> {
    const loader = pages[`./Pages/${name}.vue`];
    if (!loader) throw new Error(`Unknown page: ${name}`);
    return loader();
}

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

createInertiaApp({
    title: (title) => (title ? `${title} · POS Merchant Hunter` : 'POS Merchant Hunter'),
    resolve: (name) => resolvePage(name),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
