// css
import '../css/app.css'

// JS & Libraries
import './bootstrap'
import Alpine from 'alpinejs'
import { createApp, h } from 'vue'
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m'

// Vuetify
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as labsComponents from 'vuetify/labs/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

// Echo & Pusher
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Vue Toastification
import Toast, { useToast } from 'vue-toastification'
import 'vue-toastification/dist/index.css'

// Initialize Top-Level Code
window.Alpine = Alpine
Alpine.start()

if (window.Laravel?.user) {
    // Echo configuration...
}

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel'

// ... imports above remain unchanged ...

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const initialBranding = props.initialPage.props.branding;

        // Instantiate Vuetify with both light and dark themes
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
                defaultTheme: localStorage.getItem('user_theme_mode') || 'light',
                themes: {
                    light: {
                        colors: {
                            'starbucks-green': '#006241',
                            primary: initialBranding?.primary_color || '#1867C0',
                            secondary: initialBranding?.secondary_color || '#5C6BC0',
                        },
                    },
                    dark: {
                        colors: {
                            background: '#121212',
                            surface: '#1E1E1E',
                            'surface-variant': '#2D2D2D',
                            'starbucks-green': '#006241',
                            primary: initialBranding?.primary_color || '#1867C0',
                            secondary: initialBranding?.secondary_color || '#5C6BC0',
                        },
                    },
                },
            },
        });

        // Sync dynamic database branding to both theme palettes
        router.on('success', (event) => {
            const branding = event.detail.page.props.branding;
            if (branding) {
                if (branding.primary_color) {
                    vuetify.theme.themes.value.light.colors.primary = branding.primary_color;
                    vuetify.theme.themes.value.dark.colors.primary = branding.primary_color;
                }
                if (branding.secondary_color) {
                    vuetify.theme.themes.value.light.colors.secondary = branding.secondary_color;
                    vuetify.theme.themes.value.dark.colors.secondary = branding.secondary_color;
                }
            }
        });

        return createApp({ render: () => h(App, props) })
            .use(ZiggyVue)
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