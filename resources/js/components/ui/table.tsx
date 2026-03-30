import * as React from 'react';

import { cn } from '@/lib/utils';

type TableProps = React.ComponentProps<'table'> & {
    container?: boolean;
    containerClassName?: string;
};

function Table({
    className,
    container = true,
    containerClassName,
    ...props
}: TableProps) {
    const table = (
        <table
            data-slot="table"
            className={cn('w-full caption-bottom text-[13px]', className)}
            {...props}
        />
    );

    if (!container) {
        return table;
    }

    return (
        <div
            data-slot="table-container"
            className={cn(
                'overflow-x-auto rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)]',
                containerClassName,
            )}
        >
            {table}
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('bg-muted/60 text-left [&_tr]:border-b', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('[&_tr:last-child]:border-0', className)}
            {...props}
        />
    );
}

function TableFooter({ className, ...props }: React.ComponentProps<'tfoot'>) {
    return (
        <tfoot
            data-slot="table-footer"
            className={cn(
                'bg-muted/40 text-foreground border-t font-medium [&>tr]:last:border-b-0',
                className
            )}
            {...props}
        />
    );
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn(
                'border-b border-[color:var(--border-subtle)] transition-colors hover:bg-muted/35 data-[state=selected]:bg-primary/8',
                className
            )}
            {...props}
        />
    );
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'px-4 py-3 text-left text-[11px] font-semibold tracking-[0.08em] text-[color:var(--text-secondary)] uppercase',
                className
            )}
            {...props}
        />
    );
}

function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return (
        <td
            data-slot="table-cell"
            className={cn('px-4 py-3 align-middle text-[13px]', className)}
            {...props}
        />
    );
}

function TableCaption({
    className,
    ...props
}: React.ComponentProps<'caption'>) {
    return (
        <caption
            data-slot="table-caption"
            className={cn('mt-4 text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
};
