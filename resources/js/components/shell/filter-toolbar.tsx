import * as React from 'react';

import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function FilterToolbar({
    className,
    children,
    ...props
}: React.ComponentProps<'form'>) {
    return (
        <form
            className={cn(
                'rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-card p-4 shadow-[var(--shadow-xs)]',
                className,
            )}
            {...props}
        >
            {children}
        </form>
    );
}

export function FilterToolbarGrid({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            className={cn('grid gap-4 md:grid-cols-2 xl:grid-cols-4', className)}
            {...props}
        />
    );
}

type FilterFieldProps = React.ComponentProps<'div'> & {
    label?: React.ReactNode;
    htmlFor?: string;
    hint?: React.ReactNode;
};

export function FilterField({
    label,
    htmlFor,
    hint,
    className,
    children,
    ...props
}: FilterFieldProps) {
    return (
        <div className={cn('grid gap-2', className)} {...props}>
            {label && <Label htmlFor={htmlFor}>{label}</Label>}
            {children}
            {hint && (
                <p className="text-xs text-[color:var(--text-secondary)]">
                    {hint}
                </p>
            )}
        </div>
    );
}

export function FilterToolbarActions({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            className={cn('flex flex-wrap items-end gap-2', className)}
            {...props}
        />
    );
}
