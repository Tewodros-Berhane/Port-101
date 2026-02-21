import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import FlashToaster from './components/flash-toaster';
import { ToastProvider } from './components/ui/toast';
import { initializeTheme } from './hooks/use-appearance';
import type { SharedData } from './types';

const appName = import.meta.env.VITE_APP_NAME || 'Port-101';

type BackForwardGuardWindow = Window & {
    __portBackForwardGuardInstalled?: boolean;
};

const installBackForwardGuard = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const guardedWindow = window as BackForwardGuardWindow;

    if (guardedWindow.__portBackForwardGuardInstalled) {
        return;
    }

    window.addEventListener('pageshow', (event) => {
        const isAuthenticatedPage =
            document.documentElement.dataset.authenticated === '1';

        if (!isAuthenticatedPage) {
            return;
        }

        const navigationEntry = performance.getEntriesByType(
            'navigation',
        )[0] as PerformanceNavigationTiming | undefined;
        const isBackForwardNavigation =
            navigationEntry?.type === 'back_forward';

        if (event.persisted || isBackForwardNavigation) {
            window.location.reload();
        }
    });

    guardedWindow.__portBackForwardGuardInstalled = true;
};

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <ToastProvider>
                    <App {...props} />
                    <FlashToaster
                        initialFlash={
                            (props.initialPage.props as Partial<SharedData>)
                                .flash
                        }
                    />
                </ToastProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
installBackForwardGuard();
