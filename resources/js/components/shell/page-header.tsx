import * as React from 'react';

import { cn } from '@/lib/utils';

type PageHeaderProps = {
    title: string;
    description?: string;
    actions?: React.ReactNode;
    meta?: React.ReactNode;
    className?: string;
};

export function PageHeader({
    title,
    description,
    actions,
    meta,
    className,
}: PageHeaderProps) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 md:flex-row md:items-start md:justify-between',
                className,
            )}
        >
            <div className="min-w-0 flex-1 space-y-2">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-[-0.02em] text-foreground md:text-[28px] md:leading-8">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-3xl text-sm leading-6 text-[color:var(--text-secondary)]">
                            {description}
                        </p>
                    )}
                </div>

                {meta && (
                    <div className="flex flex-wrap items-center gap-2 text-xs text-[color:var(--text-secondary)]">
                        {meta}
                    </div>
                )}
            </div>

            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2 md:justify-end">
                    {actions}
                </div>
            )}
        </header>
    );
}
