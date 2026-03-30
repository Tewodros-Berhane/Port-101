import * as React from 'react';

import { PageHeader } from '@/components/shell/page-header';
import { cn } from '@/lib/utils';

type FormShellProps = {
    title: string;
    description?: string;
    actions?: React.ReactNode;
    meta?: React.ReactNode;
    errorSummary?: React.ReactNode;
    aside?: React.ReactNode;
    footer?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
    contentClassName?: string;
};

export function FormShell({
    title,
    description,
    actions,
    meta,
    errorSummary,
    aside,
    footer,
    children,
    className,
    contentClassName,
}: FormShellProps) {
    return (
        <div className={cn('space-y-6', className)}>
            <PageHeader
                title={title}
                description={description}
                actions={actions}
                meta={meta}
            />

            {errorSummary}

            <div
                className={cn(
                    'grid gap-6',
                    aside ? 'xl:grid-cols-[minmax(0,1fr)_320px]' : '',
                    contentClassName,
                )}
            >
                <div className="space-y-6">{children}</div>
                {aside ? <aside className="space-y-6">{aside}</aside> : null}
            </div>

            {footer}
        </div>
    );
}
