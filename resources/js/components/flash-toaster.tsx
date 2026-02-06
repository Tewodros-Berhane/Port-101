import { useToast } from '@/components/ui/toast';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function FlashToaster() {
    const { flash } = usePage<SharedData>().props;
    const { showToast } = useToast();
    const lastShown = useRef<string>('');

    useEffect(() => {
        const success = flash?.success?.trim();
        const error = flash?.error?.trim();
        const warning = flash?.warning?.trim();

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
    }, [flash?.success, flash?.error, flash?.warning, showToast]);

    return null;
}
