import { useToast } from '@/components/ui/toast';
import type { SharedData } from '@/types';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

type FlashMessages = SharedData['flash'];

function getFlashMessage(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const message = value.trim();

    return message.length > 0 ? message : null;
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
            const success = getFlashMessage(flash?.success);
            const error = getFlashMessage(flash?.error);
            const warning = getFlashMessage(flash?.warning);

            if (success) {
                const key = `success:${success}`;

                if (lastShown.current !== key) {
                    showToast(success, 'success');
                    lastShown.current = key;
                }

                return;
            }

            if (error) {
                const key = `error:${error}`;

                if (lastShown.current !== key) {
                    showToast(error, 'error');
                    lastShown.current = key;
                }

                return;
            }

            if (warning) {
                const key = `warning:${warning}`;

                if (lastShown.current !== key) {
                    showToast(warning, 'warning');
                    lastShown.current = key;
                }
            }
        },
        [showToast],
    );

    useEffect(() => {
        showFromFlash(initialFlash);

        return router.on('success', (event) => {
            const nextFlash = (event.detail.page.props as Partial<SharedData>)
                .flash;
            showFromFlash(nextFlash);
        });
    }, [initialFlash, showFromFlash]);

    return null;
}
