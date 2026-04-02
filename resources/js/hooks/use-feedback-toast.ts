import { useCallback, useMemo } from 'react';
import { useToast } from '@/components/ui/toast';
import {
    CLIENT_TOAST_HEADER,
    CLIENT_TOAST_MODE,
    resolveFlashToast,
} from '@/lib/feedback-toast';
import type { FlashMessages, ToastPayload } from '@/types';

type PageWithFlash = {
    props?: Record<string, unknown>;
};

export function useFeedbackToast() {
    const { showToast, dismissToast } = useToast();

    const clientToastHeaders = useMemo(
        () => ({
            [CLIENT_TOAST_HEADER]: CLIENT_TOAST_MODE,
        }),
        [],
    );

    const showSuccess = useCallback(
        (toast: string | ToastPayload) => showToast(toast, 'success'),
        [showToast],
    );
    const showError = useCallback(
        (toast: string | ToastPayload) => showToast(toast, 'error'),
        [showToast],
    );
    const showWarning = useCallback(
        (toast: string | ToastPayload) => showToast(toast, 'warning'),
        [showToast],
    );
    const showInfo = useCallback(
        (toast: string | ToastPayload) => showToast(toast, 'info'),
        [showToast],
    );

    const showFlashToast = useCallback(
        (flash?: FlashMessages) => {
            const toast = resolveFlashToast(flash, {
                includeSuppressed: true,
            });

            if (!toast) {
                return null;
            }

            return showToast({
                ...toast,
                suppress_global_toast: undefined,
            });
        },
        [showToast],
    );

    const showPageFlashToast = useCallback(
        (page?: PageWithFlash) => {
            const flash =
                page?.props && typeof page.props === 'object'
                    ? (page.props.flash as FlashMessages | undefined)
                    : undefined;

            return showFlashToast(flash);
        },
        [showFlashToast],
    );

    return {
        clientToastHeaders,
        dismissToast,
        showError,
        showFlashToast,
        showInfo,
        showPageFlashToast,
        showSuccess,
        showToast,
        showWarning,
    };
}
