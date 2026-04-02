export type ToastLevel = 'success' | 'error' | 'warning' | 'info';

export type ToastDuration = 'short' | 'default' | 'long' | 'persistent' | number;

export type ToastAction = {
    label: string;
    href?: string | null;
    onClick?: () => void;
};

export type ToastPayload = {
    level?: ToastLevel;
    title?: string;
    message: string;
    action?: ToastAction | null;
    duration?: ToastDuration;
    dedupe_key?: string;
    suppress_global_toast?: boolean;
};

export type FlashMessage = string | ToastPayload | null;

export type FlashMessages = Partial<Record<ToastLevel, FlashMessage>>;
