import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';
import { useToast } from '@/components/ui/toast';
import {
    flashToastIdentity,
    resolveFlashToast,
} from '@/lib/feedback-toast';
import type { FlashMessages, SharedData } from '@/types';

export default function FlashToaster({
    initialFlash,
}: {
    initialFlash?: FlashMessages;
}) {
    const { showToast } = useToast();
    const lastShown = useRef<string>('');

    const showFromFlash = useCallback(
        (flash?: FlashMessages) => {
            const toast = resolveFlashToast(flash);

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
