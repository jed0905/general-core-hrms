// javascript
import './bootstrap'

// css
import '../css/app.css'

// alpine.js
import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()

// inertia
import { createApp, h } from 'vue'
import { createInertiaApp, Link, Head } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

// ziggy routes
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
// import { ZiggyVue } from 'ziggy';

// vuetify
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as labsComponents from 'vuetify/labs/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

import Echo from 'laravel-echo';
import Pusher from 'pusher-js'; // required dependency, even if using Reverb

if (window.Laravel.user) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb', // use 'reverb' for Laravel Reverb
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: false, // set true if using HTTPS
        enabledTransports: ['ws', 'wss'],
    });
}

const vuetify = createVuetify({
    components: {
        ...components,
        ...labsComponents,
    },
    directives,
    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: {
            mdi,
        },
    },
    theme: {
        themes: {
            light: {
                colors: {
                    'starbucks-green': '#006241',
                },
            },
        },
    },
})


// vue toastification
import Toast from 'vue-toastification';
import { useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css'

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel'


createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(ZiggyVue, Ziggy)
            .use(vuetify)
            .use(Toast, {
                timeout: 3500,
                position: 'top-center',
                hideProgressBar: true,
                shareAppContext: true,
            })
            .mixin({
                methods: {
                    showToast: function (message, type = 'default') {
                        const toast = useToast()
                        // Use specific toast methods for better type recognition
                        if (type === 'success') {
                            toast.success(message)
                        } else if (type === 'error') {
                            toast.error(message)
                        } else if (type === 'warning') {
                            toast.warning(message)
                        } else if (type === 'info') {
                            toast.info(message)
                        } else {
                            toast(message, {
                                type: type,
                            })
                        }
                    },
                    triggerRouteLink: function (routeName, routeParams, visitParams) {
                        router.visit(route(routeName, routeParams), visitParams)
                    },
                },
            })
            .component('Link', Link)
            .component('Head', Head)
            .use(plugin)
            .mount(el)
    },
    progress: {
        color: '#4CAF50',
    },
})
