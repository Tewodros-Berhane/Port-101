import * as React from 'react';

import { cn } from '@/lib/utils';

type WorkspaceShellProps = {
    header: React.ReactNode;
    kpis?: React.ReactNode;
    table?: React.ReactNode;
    pagination?: React.ReactNode;
    children?: React.ReactNode;
    className?: string;
};

export function WorkspaceShell({
    header,
    kpis,
    table,
    pagination,
    children,
    className,
}: WorkspaceShellProps) {
    return (
        <div className={cn('space-y-6', className)}>
            {header}
            {kpis}
            {children}
            {table}
            {pagination}
        </div>
    );
}
