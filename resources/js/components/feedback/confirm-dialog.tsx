import { AlertTriangle, Info, ShieldAlert, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export type ConfirmDialogTone = 'default' | 'warning' | 'danger';

export type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    processingLabel?: string;
    tone?: ConfirmDialogTone;
    processing?: boolean;
    confirmDisabled?: boolean;
    onConfirm: () => void;
    helperText?: ReactNode;
    error?: ReactNode;
    children?: ReactNode;
    className?: string;
};

const toneStyles: Record<
    ConfirmDialogTone,
    {
        icon: typeof Info;
        iconClassName: string;
        panelClassName: string;
        confirmVariant: 'default' | 'destructive';
    }
> = {
    default: {
        icon: Info,
        iconClassName:
            'bg-[color:var(--status-info-soft)] text-[color:var(--status-info)]',
        panelClassName:
            'border-[color:var(--status-info)]/16 bg-[color:var(--status-info-soft)]/55',
        confirmVariant: 'default',
    },
    warning: {
        icon: AlertTriangle,
        iconClassName:
            'bg-[color:var(--status-warning-soft)] text-[color:var(--status-warning)]',
        panelClassName:
            'border-[color:var(--status-warning)]/18 bg-[color:var(--status-warning-soft)]/58',
        confirmVariant: 'default',
    },
    danger: {
        icon: ShieldAlert,
        iconClassName:
            'bg-[color:var(--status-danger-soft)] text-[color:var(--status-danger)]',
        panelClassName:
            'border-[color:var(--status-danger)]/18 bg-[color:var(--status-danger-soft)]/58',
        confirmVariant: 'destructive',
    },
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Continue',
    cancelLabel = 'Cancel',
    processingLabel = 'Working...',
    tone = 'default',
    processing = false,
    confirmDisabled = false,
    onConfirm,
    helperText,
    error,
    children,
    className,
}: ConfirmDialogProps) {
    const toneStyle = toneStyles[tone];
    const Icon = toneStyle.icon;

    const handleOpenChange = (nextOpen: boolean) => {
        if (processing && !nextOpen) {
            return;
        }

        onOpenChange(nextOpen);
    };

    const preventDismiss = (event: Event) => {
        if (processing) {
            event.preventDefault();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                className={cn('overflow-visible sm:max-w-xl', className)}
                closeDisabled={processing}
                showCloseButton={false}
                onEscapeKeyDown={preventDismiss}
                onInteractOutside={preventDismiss}
            >
                <DialogClose asChild>
                    <button
                        type="button"
                        disabled={processing}
                        className="absolute -top-3 -right-3 z-10 flex size-8 items-center justify-center rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] text-[color:var(--text-muted)] shadow-[var(--shadow-sm)] transition-[background-color,color,box-shadow,border-color] hover:border-[color:var(--border-default)] hover:bg-[color:var(--bg-surface-muted)] hover:text-[color:var(--text-primary)] focus:outline-hidden disabled:pointer-events-none disabled:opacity-50"
                        aria-label="Close dialog"
                    >
                        <X className="size-3.5" />
                    </button>
                </DialogClose>

                <div className="grid gap-5">
                    <div
                        className={cn(
                            'rounded-[var(--radius-panel)] border px-4 py-4',
                            toneStyle.panelClassName,
                        )}
                    >
                        <div className="flex items-start gap-3">
                            <div
                                className={cn(
                                    'mt-0.5 rounded-full p-2',
                                    toneStyle.iconClassName,
                                )}
                            >
                                <Icon className="size-4" />
                            </div>

                            <DialogHeader className="min-w-0 flex-1 text-left">
                                <DialogTitle>{title}</DialogTitle>
                                {description ? (
                                    <DialogDescription>
                                        {description}
                                    </DialogDescription>
                                ) : null}
                            </DialogHeader>
                        </div>
                    </div>

                    {children ? <div className="grid gap-4">{children}</div> : null}

                    {helperText ? (
                        <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                            {helperText}
                        </p>
                    ) : null}

                    {error ? (
                        <div className="rounded-[var(--radius-panel)] border border-[color:var(--status-danger)]/18 bg-[color:var(--status-danger-soft)] px-4 py-3 text-sm text-[color:var(--status-danger-foreground)]">
                            {error}
                        </div>
                    ) : null}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            {cancelLabel}
                        </Button>
                        <Button
                            type="button"
                            variant={toneStyle.confirmVariant}
                            disabled={processing || confirmDisabled}
                            onClick={onConfirm}
                        >
                            {processing ? processingLabel : confirmLabel}
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
