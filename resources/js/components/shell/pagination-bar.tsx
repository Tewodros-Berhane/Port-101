import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationBarProps = {
    links: PaginationLink[];
    className?: string;
};

export function PaginationBar({ links, className }: PaginationBarProps) {
    if (links.length <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className={cn('flex flex-wrap items-center gap-2', className)}
        >
            {links.map((link) => (
                <Link
                    key={link.label}
                    href={link.url ?? '#'}
                    preserveScroll
                    aria-current={link.active ? 'page' : undefined}
                    className={cn(
                        'inline-flex h-9 items-center rounded-[var(--radius-control)] border px-3 text-sm font-medium transition-[background-color,border-color,color] duration-150',
                        link.active
                            ? 'border-primary bg-primary/8 text-primary'
                            : 'border-[color:var(--border-subtle)] bg-card text-[color:var(--text-secondary)] hover:border-[color:var(--border-strong)] hover:text-foreground',
                        !link.url && 'pointer-events-none opacity-50',
                    )}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}
