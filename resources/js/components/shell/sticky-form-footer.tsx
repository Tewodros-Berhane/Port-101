import * as React from 'react';

import { cn } from '@/lib/utils';

type StickyFormFooterProps = {
    primaryActions: React.ReactNode;
    secondaryActions?: React.ReactNode;
    meta?: React.ReactNode;
    className?: string;
};

export function StickyFormFooter({
    primaryActions,
    secondaryActions,
    meta,
    className,
}: StickyFormFooterProps) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-20 border-t border-[color:var(--border-subtle)] bg-[color:var(--bg-app)] py-4 backdrop-blur',
                className,
            )}
        >
            <div className="flex flex-col gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] px-5 py-4 shadow-[var(--shadow-sm)] md:flex-row md:items-center md:justify-between">
                <div className="flex min-w-0 flex-col gap-3 md:flex-row md:items-center">
                    {secondaryActions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {secondaryActions}
                        </div>
                    )}

                    {meta && (
                        <div className="text-xs leading-5 text-[color:var(--text-secondary)]">
                            {meta}
                        </div>
                    )}
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2 md:justify-end">
                    {primaryActions}
                </div>
            </div>
        </div>
    );
}
