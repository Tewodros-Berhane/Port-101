import type { InertiaLinkProps } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import PublicReveal from '@/components/public/public-reveal';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type PublicHref = NonNullable<InertiaLinkProps['href']>;

export type PublicNavLink = {
    label: string;
    href: string;
};

export type PublicFooterGroup = {
    title: string;
    links: PublicNavLink[];
};

export function PublicSection({
    id,
    className,
    tone = 'default',
    children,
}: PropsWithChildren<{
    id?: string;
    className?: string;
    tone?: 'default' | 'muted';
}>) {
    return (
        <section
            id={id}
            className={cn(
                'scroll-mt-24 py-20 sm:py-24',
                tone === 'muted' && 'bg-[color:var(--bg-surface-muted)]',
                className,
            )}
        >
            <PublicReveal className="mx-auto w-full max-w-7xl px-6 lg:px-8">
                {children}
            </PublicReveal>
        </section>
    );
}

export function PublicSectionHeader({
    eyebrow,
    title,
    description,
    align = 'left',
    badge,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    align?: 'left' | 'center';
    badge?: ReactNode;
}) {
    return (
        <div
            className={cn(
                'max-w-3xl space-y-4',
                align === 'center' && 'mx-auto text-center',
            )}
        >
            {badge ? badge : null}
            {eyebrow ? (
                <div>
                    <Badge variant="secondary" className="px-3 py-1">
                        {eyebrow}
                    </Badge>
                </div>
            ) : null}
            <h2 className="text-balance text-3xl font-semibold tracking-[-0.03em] text-foreground sm:text-4xl lg:text-[2.75rem] lg:leading-[1.05]">
                {title}
            </h2>
            {description ? (
                <p className="max-w-2xl text-[15px] leading-7 text-[color:var(--text-secondary)] sm:text-base">
                    {description}
                </p>
            ) : null}
        </div>
    );
}

export function PublicEyebrow({
    children,
    className,
}: PropsWithChildren<{ className?: string }>) {
    return (
        <Badge variant="outline" className={cn('px-3 py-1', className)}>
            {children}
        </Badge>
    );
}
