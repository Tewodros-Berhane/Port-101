import { cn } from '@/lib/utils';
import {
    createContext,
    type PropsWithChildren,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';

type ToastVariant = 'success' | 'error' | 'warning';

type ToastItem = {
    id: string;
    message: string;
    variant: ToastVariant;
};

type ToastContextValue = {
    showToast: (message: string, variant?: ToastVariant) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

const variantClasses: Record<ToastVariant, string> = {
    success:
        'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100',
    error:
        'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
    warning:
        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
};

function ToastViewport({
    toasts,
    onDismiss,
}: {
    toasts: ToastItem[];
    onDismiss: (id: string) => void;
}) {
    return (
        <div className="pointer-events-none fixed top-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={cn(
                        'pointer-events-auto rounded-lg border px-4 py-3 text-sm shadow-lg',
                        variantClasses[toast.variant],
                    )}
                    role="status"
                    aria-live="polite"
                >
                    <div className="flex items-start justify-between gap-3">
                        <p>{toast.message}</p>
                        <button
                            type="button"
                            className="opacity-70 transition hover:opacity-100"
                            onClick={() => onDismiss(toast.id)}
                            aria-label="Dismiss notification"
                        >
                            ×
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}

export function ToastProvider({ children }: PropsWithChildren) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const dismissToast = useCallback((id: string) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    }, []);

    const showToast = useCallback(
        (message: string, variant: ToastVariant = 'success') => {
            const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;

            setToasts((current) => [...current, { id, message, variant }]);

            window.setTimeout(() => {
                dismissToast(id);
            }, 4000);
        },
        [dismissToast],
    );

    const value = useMemo(() => ({ showToast }), [showToast]);

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
