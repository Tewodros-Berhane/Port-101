import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

const DEFAULT_MESSAGE =
    'You have unsaved changes. Leave this page without saving?';

type UseUnsavedChangesGuardOptions = {
    enabled: boolean;
    message?: string;
};

export function useUnsavedChangesGuard({
    enabled,
    message = DEFAULT_MESSAGE,
}: UseUnsavedChangesGuardOptions) {
    const enabledRef = useRef(enabled);
    const bypassNextNavigationRef = useRef(false);

    useEffect(() => {
        enabledRef.current = enabled;
    }, [enabled]);

    useEffect(() => {
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            if (!enabledRef.current || bypassNextNavigationRef.current) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        };

        const removeBefore = router.on('before', () => {
            if (!enabledRef.current || bypassNextNavigationRef.current) {
                return true;
            }

            return window.confirm(message);
        });

        const clearBypass = () => {
            bypassNextNavigationRef.current = false;
        };

        const removeFinish = router.on('finish', clearBypass);
        const removeException = router.on('exception', clearBypass);
        const removeInvalid = router.on('invalid', clearBypass);

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBefore();
            removeFinish();
            removeException();
            removeInvalid();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [message]);

    const allowNextNavigation = useCallback(() => {
        bypassNextNavigationRef.current = true;
    }, []);

    return { allowNextNavigation };
}
