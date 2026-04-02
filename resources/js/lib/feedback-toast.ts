import type { FlashMessages, ToastLevel, ToastPayload } from '@/types';

export const CLIENT_TOAST_HEADER = 'X-Port101-Feedback';
export const CLIENT_TOAST_MODE = 'client-toast';

export type NormalizedFlashToast = ToastPayload & {
    level: ToastLevel;
    message: string;
};

const FLASH_PRIORITY: ToastLevel[] = ['success', 'error', 'warning', 'info'];

export function normalizeFlashToast(
    value: FlashMessages[ToastLevel],
    level: ToastLevel,
): NormalizedFlashToast | null {
    if (typeof value === 'string') {
        const message = value.trim();

        return message === ''
            ? null
            : {
                  level,
                  message,
              };
    }

    if (!value || typeof value !== 'object') {
        return null;
    }

    const message =
        typeof value.message === 'string' ? value.message.trim() : '';

    if (message === '') {
        return null;
    }

    return {
        ...value,
        level: value.level ?? level,
        message,
        title:
            typeof value.title === 'string'
                ? value.title.trim() || undefined
                : undefined,
        dedupe_key:
            typeof value.dedupe_key === 'string'
                ? value.dedupe_key.trim() || undefined
                : undefined,
    };
}

export function resolveFlashToast(
    flash?: FlashMessages,
    options: { includeSuppressed?: boolean } = {},
): NormalizedFlashToast | null {
    for (const level of FLASH_PRIORITY) {
        const toast = normalizeFlashToast(flash?.[level], level);

        if (!toast) {
            continue;
        }

        if (!options.includeSuppressed && toast.suppress_global_toast) {
            continue;
        }

        return toast;
    }

    return null;
}

export function flashToastIdentity(toast: NormalizedFlashToast): string {
    return (
        toast.dedupe_key
        || [
            toast.level,
            toast.title ?? '',
            toast.message,
            toast.action?.href ?? '',
        ].join(':')
    );
}
