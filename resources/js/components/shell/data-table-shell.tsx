import * as React from 'react';

import { cn } from '@/lib/utils';

type DataTableShellProps = {
    header?: React.ReactNode;
    footer?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    bodyClassName?: string;
};

export function DataTableShell({
    header,
    footer,
    children,
    className,
    bodyClassName,
}: DataTableShellProps) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-card shadow-[var(--shadow-xs)]',
                className,
            )}
        >
            {header && (
                <div className="border-b border-[color:var(--border-subtle)] px-5 py-4">
                    {header}
                </div>
            )}

            <div className={cn('overflow-x-auto', bodyClassName)}>{children}</div>

            {footer && (
                <div className="border-t border-[color:var(--border-subtle)] px-5 py-4">
                    {footer}
                </div>
            )}
        </section>
    );
}
