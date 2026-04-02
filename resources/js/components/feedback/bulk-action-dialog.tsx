import type { ReactNode } from 'react';
import {
    ConfirmDialog,
    type ConfirmDialogProps,
} from '@/components/feedback/confirm-dialog';

type BulkActionDialogProps = Omit<
    ConfirmDialogProps,
    'children' | 'tone'
> & {
    itemCount: number;
    itemLabel: string;
    summary?: ReactNode;
};

export function BulkActionDialog({
    itemCount,
    itemLabel,
    summary,
    ...props
}: BulkActionDialogProps) {
    return (
        <ConfirmDialog tone="warning" {...props}>
            <div className="grid gap-4">
                <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-4">
                    <p className="text-sm font-medium text-foreground">
                        {itemCount} {itemLabel}
                    </p>
                    {summary ? (
                        <div className="mt-2 text-sm leading-6 text-[color:var(--text-secondary)]">
                            {summary}
                        </div>
                    ) : null}
                </div>
            </div>
        </ConfirmDialog>
    );
}
