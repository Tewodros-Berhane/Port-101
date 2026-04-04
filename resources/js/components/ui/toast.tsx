import { Link } from '@inertiajs/react';
import { CheckCircle2, CircleX, Info, TriangleAlert, X } from 'lucide-react';
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

const levelStyles: Record<
    ToastLevel,
    {
        accent: string;
        icon: string;
        action: string;
    }
> = {
    success: {
        accent: 'bg-[color:var(--status-success)]',
        icon: 'text-[color:var(--status-success)]',
        action: 'text-[color:var(--status-success)]',
    },
    error: {
        accent: 'bg-[color:var(--status-danger)]',
        icon: 'text-[color:var(--status-danger)]',
        action: 'text-[color:var(--status-danger)]',
    },
    warning: {
        accent: 'bg-[color:var(--status-warning)]',
        icon: 'text-[color:var(--status-warning)]',
        action: 'text-[color:var(--status-warning)]',
    },
    info: {
        accent: 'bg-[color:var(--status-info)]',
        icon: 'text-[color:var(--status-info)]',
        action: 'text-[color:var(--status-info)]',
    },
};

const iconMap: Record<ToastLevel, typeof CheckCircle2> = {
    success: CheckCircle2,
    error: CircleX,
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
            className="pointer-events-none fixed right-4 bottom-4 z-[100] flex w-full max-w-sm flex-col gap-2.5 px-4 sm:px-0"
            aria-live="polite"
            aria-relevant="additions text"
        >
            {toasts.map((toast) => {
                const Icon = iconMap[toast.level];
                const styles = levelStyles[toast.level];

                return (
                    <div
                        key={toast.id}
                        className={cn(
                            'pointer-events-auto overflow-hidden rounded-[calc(var(--radius-hero)+2px)] border border-[color:var(--border-default)] bg-[color:var(--bg-surface-elevated)] text-[color:var(--text-primary)] shadow-[var(--shadow-md)] animate-in fade-in-0 zoom-in-95 slide-in-from-bottom-2 slide-in-from-right-4 duration-200 motion-reduce:animate-none',
                        )}
                        role={toast.level === 'error' ? 'alert' : 'status'}
                        aria-live={toast.level === 'error' ? 'assertive' : 'polite'}
                    >
                        <div className="flex items-stretch">
                            <div className={cn('w-1 shrink-0', styles.accent)} />

                            <div className="flex min-w-0 flex-1 items-start gap-2.5 px-3.5 py-3.5">
                                <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]">
                                    <Icon className={cn('size-[18px]', styles.icon)} />
                                </div>

                                <div className="min-w-0 flex-1 space-y-2.5">
                                    <div className="space-y-0.5">
                                        {toast.title ? (
                                            <p className="text-[13px] font-semibold tracking-[-0.01em] text-[color:var(--text-primary)]">
                                                {toast.title}
                                            </p>
                                        ) : null}
                                        <p className="text-[13px] leading-5 text-[color:var(--text-secondary)]">
                                            {toast.message}
                                        </p>
                                    </div>

                                    {toast.action?.href || toast.action?.onClick ? (
                                        <div>
                                            {toast.action.href ? (
                                                <Link
                                                    href={toast.action.href}
                                                    className={cn(
                                                        'inline-flex text-[13px] font-medium underline underline-offset-4 transition hover:opacity-85',
                                                        styles.action,
                                                    )}
                                                    onClick={() => onDismiss(toast.id)}
                                                >
                                                    {toast.action.label}
                                                </Link>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className={cn(
                                                        'inline-flex text-[13px] font-medium underline underline-offset-4 transition hover:opacity-85',
                                                        styles.action,
                                                    )}
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
                                    className="rounded-[var(--radius-control)] p-0.5 text-[color:var(--text-muted)] transition hover:bg-[color:var(--bg-surface-muted)] hover:text-[color:var(--text-primary)]"
                                    onClick={() => onDismiss(toast.id)}
                                    aria-label="Dismiss notification"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
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
