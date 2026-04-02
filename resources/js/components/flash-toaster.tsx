import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';
import { useToast } from '@/components/ui/toast';
import type { FlashMessage, FlashMessages, SharedData, ToastLevel, ToastPayload } from '@/types';

function normalizeFlashToast(
    value: FlashMessage | undefined,
    level: ToastLevel,
): ToastPayload | null {
    if (typeof value === 'string') {
        const message = value.trim();

        if (message === '') {
            return null;
        }

        return {
            level,
            message,
        };
    }

    if (!value || typeof value !== 'object') {
        return null;
    }

    const message = value.message.trim();

    if (message === '') {
        return null;
    }

    return {
        ...value,
        level: value.level ?? level,
        message,
    };
}

function flashToastIdentity(toast: ToastPayload): string {
    return (
        toast.dedupe_key ??
        [toast.level ?? 'success', toast.title ?? '', toast.message].join(':')
    );
}

export default function FlashToaster({
    initialFlash,
}: {
    initialFlash?: FlashMessages;
}) {
    const { showToast } = useToast();
    const lastShown = useRef<string>('');

    const showFromFlash = useCallback(
        (flash?: FlashMessages) => {
            const toast =
                normalizeFlashToast(flash?.success, 'success') ??
                normalizeFlashToast(flash?.error, 'error') ??
                normalizeFlashToast(flash?.warning, 'warning') ??
                normalizeFlashToast(flash?.info, 'info');

            if (!toast) {
                return;
            }

            const key = flashToastIdentity(toast);

            if (lastShown.current !== key) {
                showToast(toast);
                lastShown.current = key;
            }
        },
        [showToast],
    );

    useEffect(() => {
        showFromFlash(initialFlash);

        return router.on('success', (event) => {
            const nextFlash = (event.detail.page.props as Partial<SharedData>).flash;
            showFromFlash(nextFlash);
        });
    }, [initialFlash, showFromFlash]);

    return null;
}
