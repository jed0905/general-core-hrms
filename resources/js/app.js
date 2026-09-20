// CSS
import '../css/app.css'

// JS & Libraries
import './bootstrap'
import Alpine from 'alpinejs'
import { createApp, h, ref, reactive } from 'vue'
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
import { VOverlay, VProgressCircular, VSnackbar, VIcon, VBtn } from 'vuetify/components'

// Echo & Pusher
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Initialize Top-Level Code
window.Alpine = Alpine
Alpine.start()

if (window.Laravel?.user) {
    // Echo configuration...
}

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel'

// Global navigation state for centered loading spinner
const isNavigating = ref(false)

router.on('start', () => { isNavigating.value = true })
router.on('finish', () => { isNavigating.value = false })
router.on('cancel', () => { isNavigating.value = false })

// Global Toast State (VSnackbar)
const toastState = reactive({
    show: false,
    message: '',
    color: 'primary',
    icon: 'mdi-bell-outline',
    timeout: 3000,
})

const triggerToast = (message, type = 'default', timeout = 3000) => {
    const toastConfigs = {
        success: { color: 'success', icon: 'mdi-check-circle-outline' },
        error: { color: 'error', icon: 'mdi-alert-circle-outline' },
        warning: { color: 'warning', icon: 'mdi-alert-outline' },
        info: { color: 'info', icon: 'mdi-information-outline' },
        default: { color: 'primary', icon: 'mdi-bell-outline' },
    }

    const config = toastConfigs[type] || toastConfigs.default

    toastState.message = message
    toastState.color = config.color
    toastState.icon = config.icon
    toastState.timeout = timeout
    toastState.show = true
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    progress: false,
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

        return createApp({
            render: () =>
                h('div', [
                    // Render Inertia App
                    h(App, props),

                    // Render Centered Vuetify Loading Overlay
                    h(
                        VOverlay,
                        {
                            modelValue: isNavigating.value,
                            class: 'align-center justify-center',
                            persistent: true,
                            scrim: '#000000',
                            opacity: 0.3,
                            zIndex: 99999,
                        },
                        {
                            default: () =>
                                h(VProgressCircular, {
                                    color: 'primary',
                                    size: 64,
                                    width: 5,
                                    indeterminate: true,
                                }),
                        }
                    ),

                    // Top-Right VSnackbar
                    h(
                        VSnackbar,
                        {
                            modelValue: toastState.show,
                            'onUpdate:modelValue': (val) => { toastState.show = val },
                            color: toastState.color,
                            timeout: toastState.timeout,
                            location: 'top right',
                            variant: 'elevated',
                            elevation: 4,
                            rounded: 'md',
                            density: 'compact',
                            style: {
                                position: 'fixed',
                                top: '16px',
                                right: '16px',
                                left: 'auto',
                                bottom: 'auto',
                                maxWidth: '320px',
                                minWidth: 'auto',
                                zIndex: 100000,
                            },
                        },
                        {
                            default: () =>
                                h('div', { class: 'd-flex align-center ga-2 py-0 px-0 w-100' }, [
                                    h(VIcon, {
                                        icon: toastState.icon,
                                        size: '18',
                                        class: 'flex-shrink-0',
                                    }),
                                    h(
                                        'span',
                                        { class: 'text-caption font-weight-medium flex-grow-1' },
                                        toastState.message
                                    ),
                                    h(VBtn, {
                                        icon: 'mdi-close',
                                        variant: 'text',
                                        density: 'compact',
                                        size: 'x-small',
                                        color: 'inherit',
                                        onClick: () => { toastState.show = false },
                                    }),
                                ]),
                        }
                    ),
                ]),
        })
            .use(ZiggyVue)
            .use(vuetify)
            .mixin({
                methods: {
                    showToast: function (message, type = 'default') {
                        triggerToast(message, type)
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
})