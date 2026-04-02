import { Link } from '@inertiajs/react';
import { CheckCircle2, Info, OctagonAlert, TriangleAlert, X } from 'lucide-react';
import {
    createContext,
    type PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { cn } from '@/lib/utils';
import type { ToastDuration, ToastLevel, ToastPayload } from '@/types';

type ToastInput = string | ToastPayload;

type ToastItem = {
    id: string;
    level: ToastLevel;
    title?: string;
    message: string;
    action?: ToastPayload['action'];
    duration: ToastDuration;
    dedupe_key?: string;
};

type ToastContextValue = {
    showToast: (toast: ToastInput, level?: ToastLevel) => string;
    dismissToast: (id: string) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

const levelClasses: Record<ToastLevel, string> = {
    success:
        'border-[color:var(--status-success)]/18 bg-[color:var(--status-success-soft)] text-[color:var(--status-success-foreground)]',
    error:
        'border-[color:var(--status-danger)]/18 bg-[color:var(--status-danger-soft)] text-[color:var(--status-danger-foreground)]',
    warning:
        'border-[color:var(--status-warning)]/18 bg-[color:var(--status-warning-soft)] text-[color:var(--status-warning-foreground)]',
    info:
        'border-[color:var(--status-info)]/18 bg-[color:var(--status-info-soft)] text-[color:var(--status-info-foreground)]',
};

const iconMap: Record<ToastLevel, typeof CheckCircle2> = {
    success: CheckCircle2,
    error: OctagonAlert,
    warning: TriangleAlert,
    info: Info,
};

function resolveDuration(duration: ToastDuration): number | null {
    if (typeof duration === 'number') {
        return duration > 0 ? duration : null;
    }

    return {
        short: 2500,
        default: 4000,
        long: 6500,
        persistent: null,
    }[duration];
}

function normalizeToastInput(input: ToastInput, fallbackLevel: ToastLevel): ToastItem {
    const generatedId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    if (typeof input === 'string') {
        return {
            id: generatedId,
            level: fallbackLevel,
            message: input,
            duration: 'default',
        };
    }

    return {
        id: generatedId,
        level: input.level ?? fallbackLevel,
        title: input.title?.trim() || undefined,
        message: input.message.trim(),
        action: input.action ?? undefined,
        duration: input.duration ?? 'default',
        dedupe_key: input.dedupe_key?.trim() || undefined,
    };
}

function ToastViewport({
    toasts,
    onDismiss,
}: {
    toasts: ToastItem[];
    onDismiss: (id: string) => void;
}) {
    return (
        <div
            className="pointer-events-none fixed top-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2 px-4 sm:px-0"
            aria-live="polite"
            aria-relevant="additions text"
        >
            {toasts.map((toast) => {
                const Icon = iconMap[toast.level];

                return (
                    <div
                        key={toast.id}
                        className={cn(
                            'pointer-events-auto rounded-[var(--radius-panel)] border px-4 py-3 text-sm shadow-[var(--shadow-md)]',
                            levelClasses[toast.level],
                        )}
                        role={toast.level === 'error' ? 'alert' : 'status'}
                        aria-live={toast.level === 'error' ? 'assertive' : 'polite'}
                    >
                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 rounded-full bg-black/5 p-1 dark:bg-white/10">
                                <Icon className="size-4" />
                            </div>

                            <div className="min-w-0 flex-1 space-y-3">
                                <div className="space-y-1">
                                    {toast.title ? (
                                        <p className="font-semibold tracking-[-0.01em]">
                                            {toast.title}
                                        </p>
                                    ) : null}
                                    <p className="leading-6">{toast.message}</p>
                                </div>

                                {toast.action?.href || toast.action?.onClick ? (
                                    <div>
                                        {toast.action.href ? (
                                            <Link
                                                href={toast.action.href}
                                                className="inline-flex text-sm font-medium underline underline-offset-4 opacity-90 transition hover:opacity-100"
                                                onClick={() => onDismiss(toast.id)}
                                            >
                                                {toast.action.label}
                                            </Link>
                                        ) : (
                                            <button
                                                type="button"
                                                className="inline-flex text-sm font-medium underline underline-offset-4 opacity-90 transition hover:opacity-100"
                                                onClick={() => {
                                                    toast.action?.onClick?.();
                                                    onDismiss(toast.id);
                                                }}
                                            >
                                                {toast.action.label}
                                            </button>
                                        )}
                                    </div>
                                ) : null}
                            </div>

                            <button
                                type="button"
                                className="rounded-[var(--radius-control)] p-1 opacity-70 transition hover:bg-black/5 hover:opacity-100 dark:hover:bg-white/10"
                                onClick={() => onDismiss(toast.id)}
                                aria-label="Dismiss notification"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export function ToastProvider({ children }: PropsWithChildren) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const timeoutRefs = useRef(new Map<string, number>());

    const dismissToast = useCallback((id: string) => {
        const timeout = timeoutRefs.current.get(id);

        if (timeout) {
            window.clearTimeout(timeout);
            timeoutRefs.current.delete(id);
        }

        setToasts((current) => current.filter((toast) => toast.id !== id));
    }, []);

    const showToast = useCallback(
        (toast: ToastInput, level: ToastLevel = 'success') => {
            const nextToast = normalizeToastInput(toast, level);
            const activeId = nextToast.dedupe_key ?? nextToast.id;

            setToasts((current) => {
                const existingIndex = nextToast.dedupe_key
                    ? current.findIndex(
                          (item) => item.dedupe_key === nextToast.dedupe_key,
                      )
                    : -1;

                const nextState = [...current];

                if (existingIndex >= 0) {
                    nextState[existingIndex] = {
                        ...nextToast,
                        id: activeId,
                    };

                    return nextState;
                }

                return [...current, { ...nextToast, id: activeId }];
            });

            const existingTimeout = timeoutRefs.current.get(activeId);

            if (existingTimeout) {
                window.clearTimeout(existingTimeout);
            }

            const timeout = resolveDuration(nextToast.duration);

            if (timeout !== null) {
                timeoutRefs.current.set(
                    activeId,
                    window.setTimeout(() => {
                        dismissToast(activeId);
                    }, timeout),
                );
            } else {
                timeoutRefs.current.delete(activeId);
            }

            return activeId;
        },
        [dismissToast],
    );

    useEffect(
        () => () => {
            timeoutRefs.current.forEach((timeout) => {
                window.clearTimeout(timeout);
            });
            timeoutRefs.current.clear();
        },
        [],
    );

    const value = useMemo(
        () => ({ showToast, dismissToast }),
        [dismissToast, showToast],
    );

    return (
        <ToastContext.Provider value={value}>
            {children}
            <ToastViewport toasts={toasts} onDismiss={dismissToast} />
        </ToastContext.Provider>
    );
}

export function useToast() {
    const context = useContext(ToastContext);

    if (!context) {
        throw new Error('useToast must be used within ToastProvider');
    }

    return context;
}
