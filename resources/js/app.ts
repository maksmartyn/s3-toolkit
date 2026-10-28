import { createInertiaApp } from '@inertiajs/vue3';
import { i18nVue } from 'laravel-vue-i18n';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { configureEcho } from '@laravel/echo-vue';

// Адрес Reverb задаёт сервер (config/s3toolkit.php), чтобы собранный фронтенд не зависел от окружения
const websocket = JSON.parse(
    document.querySelector<HTMLMetaElement>('meta[name=websocket]')?.content ??
        '{}',
) as { key?: string; host?: string; port?: number; scheme?: string };

configureEcho({
    broadcaster: 'reverb',
    key: websocket.key,
    wsHost: websocket.host,
    wsPort: websocket.port,
    wssPort: websocket.port,
    forceTLS: websocket.scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        // Переводы из lang/*.json; php-файлы lang/ru/*.php собирает плагин laravel-vue-i18n/vite в php_ru.json
        const translations = import.meta.glob<{
            default: Record<string, string>;
        }>('../../lang/*.json', { eager: true });

        app.use(i18nVue, {
            lang: (page.props.locale as string | undefined) ?? 'ru',
            fallbackLang: 'ru',
            resolve: (lang: string) =>
                translations[`../../lang/${lang}.json`]?.default ?? {},
        });

        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
