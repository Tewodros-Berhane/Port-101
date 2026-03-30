import { Link, usePage } from '@inertiajs/react';
import { LockKeyhole, ShieldCheck } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';

import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps, SharedData } from '@/types';

type AuthHighlight = {
    title: string;
    description: string;
};

type AuthSplitLayoutProps = AuthLayoutProps &
    PropsWithChildren & {
        eyebrow?: string;
        panelTitle?: string;
        panelDescription?: string;
        highlights?: AuthHighlight[];
        securityNote?: string;
        helpNote?: ReactNode;
        cardClassName?: string;
        contentClassName?: string;
        variant?: 'default' | 'editorial';
        showCardBrand?: boolean;
    };

function BrandMark({
    name,
    editorial = false,
}: {
    name: string;
    editorial?: boolean;
}) {
    return (
        <div className="flex items-center gap-3">
            <div
                className={cn(
                    'flex size-11 items-center justify-center rounded-[var(--radius-hero)] border shadow-[var(--shadow-xs)]',
                    editorial
                        ? 'border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] text-foreground'
                        : 'border-[color:var(--border-subtle)] bg-primary text-primary-foreground',
                )}
            >
                <AppLogoIcon
                    className={cn(
                        'size-5',
                        editorial ? 'text-foreground' : 'text-primary-foreground',
                    )}
                />
            </div>
            <div className="min-w-0">
                <div
                    className={cn(
                        'truncate text-sm font-semibold tracking-[-0.01em]',
                        editorial ? 'text-foreground' : 'text-foreground',
                    )}
                >
                    {name}
                </div>
                <div
                    className={cn(
                        'text-xs',
                        editorial
                            ? 'text-[color:var(--text-secondary)]'
                            : 'text-[color:var(--text-secondary)]',
                    )}
                >
                    Enterprise operations workspace
                </div>
            </div>
        </div>
    );
}

export default function AuthSplitLayout({
    children,
    title,
    description,
    eyebrow,
    panelTitle,
    panelDescription,
    highlights = [],
    securityNote,
    helpNote,
    cardClassName,
    contentClassName,
    variant = 'default',
    showCardBrand = false,
}: AuthSplitLayoutProps) {
    const { name, company } = usePage<SharedData>().props;
    const workspaceName =
        company && typeof company.name === 'string' ? company.name : null;
    const desktopHighlights = highlights.slice(0, 2);
    const mobileHighlights = highlights.slice(0, 1);
    const isEditorial = variant === 'editorial';

    return (
        <div
            className={cn(
                'h-svh overflow-hidden',
                isEditorial ? 'bg-background' : 'bg-background',
            )}
        >
            <div
                className={cn(
                    'grid h-svh w-full',
                    isEditorial
                        ? 'lg:grid-cols-[minmax(0,1.02fr)_minmax(28rem,32rem)] xl:grid-cols-[minmax(0,1fr)_minmax(30rem,34rem)]'
                        : 'lg:grid-cols-[minmax(0,0.95fr)_minmax(30rem,34rem)] xl:grid-cols-[minmax(0,0.92fr)_minmax(32rem,36rem)]',
                )}
            >
                <aside
                    className={cn(
                        'hidden lg:flex',
                        isEditorial
                            ? 'border-r border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]'
                            : 'border-r border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]',
                    )}
                >
                    <div
                        className={cn(
                            'relative z-10 flex h-svh w-full flex-col justify-between',
                            isEditorial
                                ? 'px-8 py-8 xl:px-12 xl:py-10'
                                : 'px-8 py-7 xl:px-10 xl:py-8',
                        )}
                    >
                        <div className={cn(isEditorial ? 'space-y-9' : 'space-y-7')}>
                            <Link href={home()} className="inline-flex">
                                <BrandMark name={name} editorial={isEditorial} />
                            </Link>

                            <div
                                className={cn(
                                    isEditorial ? 'max-w-xl space-y-5' : 'max-w-lg space-y-4',
                                )}
                            >
                                {eyebrow ? (
                                    <div
                                        className={cn(
                                            'inline-flex items-center',
                                            isEditorial
                                                ? 'text-[12px] font-medium tracking-[-0.01em] text-[color:var(--text-secondary)]'
                                                : 'rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] px-3 py-1 text-[11px] font-semibold tracking-[0.12em] text-[color:var(--text-secondary)] uppercase shadow-[var(--shadow-xs)]',
                                        )}
                                    >
                                        {eyebrow}
                                    </div>
                                ) : null}

                                {workspaceName ? (
                                    <div
                                        className={cn(
                                            'text-sm',
                                            isEditorial
                                                ? 'text-[color:var(--text-secondary)]'
                                                : 'text-[color:var(--text-secondary)]',
                                        )}
                                    >
                                        Workspace:{' '}
                                        <span
                                            className={cn(
                                                'font-medium',
                                                isEditorial
                                                    ? 'text-foreground'
                                                    : 'text-foreground',
                                            )}
                                        >
                                            {workspaceName}
                                        </span>
                                    </div>
                                ) : null}

                                <div className={cn(isEditorial ? 'space-y-3.5' : 'space-y-3')}>
                                    <h1
                                        className={cn(
                                            'max-w-xl font-semibold tracking-[-0.03em]',
                                            isEditorial
                                                ? 'text-[2rem] leading-[1.08] text-foreground xl:max-w-[36rem] xl:text-[2.35rem]'
                                                : 'text-[2rem] leading-tight text-foreground xl:text-[2.4rem]',
                                        )}
                                    >
                                        {panelTitle}
                                    </h1>
                                    {panelDescription ? (
                                        <p
                                            className={cn(
                                                isEditorial
                                                    ? 'max-w-[30rem] text-[15px] leading-6 text-[color:var(--text-secondary)]'
                                                    : 'max-w-md text-[14px] leading-6 text-[color:var(--text-secondary)]',
                                            )}
                                        >
                                            {panelDescription}
                                        </p>
                                    ) : null}
                                </div>

                                {desktopHighlights.length > 0 ? (
                                    <ul
                                        className={cn(
                                            isEditorial
                                                ? 'grid divide-y divide-[color:var(--border-subtle)] border-y border-[color:var(--border-subtle)]'
                                                : 'grid gap-3',
                                        )}
                                    >
                                        {desktopHighlights.map((highlight) => (
                                            <li
                                                key={highlight.title}
                                                className={cn(
                                                    isEditorial
                                                        ? 'py-3.5'
                                                        : 'rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] p-3 shadow-[var(--shadow-xs)]',
                                                )}
                                            >
                                                <div className="flex items-start gap-3">
                                                    <div
                                                        className={cn(
                                                            'mt-1 shrink-0',
                                                            isEditorial
                                                                ? 'h-1.5 w-1.5 rounded-full bg-primary'
                                                                : 'mt-0.5 flex size-7 items-center justify-center rounded-full bg-primary/10 text-primary',
                                                        )}
                                                    >
                                                        {isEditorial ? null : (
                                                            <ShieldCheck className="size-3.5" />
                                                        )}
                                                    </div>
                                                    <div className="space-y-1">
                                                        <h2
                                                            className={cn(
                                                                'text-sm font-semibold tracking-[-0.01em]',
                                                                isEditorial
                                                                    ? 'text-foreground'
                                                                    : 'text-foreground',
                                                            )}
                                                        >
                                                            {highlight.title}
                                                        </h2>
                                                        <p
                                                            className={cn(
                                                                isEditorial
                                                                    ? 'max-w-[28rem] text-[13px] leading-5 text-[color:var(--text-secondary)]'
                                                                    : 'text-[13px] leading-[1.35rem] text-[color:var(--text-secondary)]',
                                                            )}
                                                        >
                                                            {highlight.description}
                                                        </p>
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                            </div>
                        </div>

                        {securityNote && (
                            <div
                                className={cn(
                                    'max-w-md',
                                    isEditorial
                                        ? 'border-t border-[color:var(--border-subtle)] pt-5'
                                        : 'rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] p-3.5 shadow-[var(--shadow-xs)]',
                                )}
                            >
                                <div className="flex items-start gap-3">
                                    <div
                                        className={cn(
                                            'mt-0.5 flex shrink-0 items-center justify-center',
                                            isEditorial
                                                ? 'size-7 rounded-full border border-[color:var(--border-subtle)] text-primary'
                                                : 'size-8 rounded-full bg-primary/10 text-primary',
                                        )}
                                    >
                                        <LockKeyhole className="size-3.5" />
                                    </div>
                                    <p
                                        className={cn(
                                            isEditorial
                                                ? 'max-w-[28rem] text-[12.5px] leading-5 text-[color:var(--text-secondary)]'
                                                : 'text-[13px] leading-[1.35rem] text-[color:var(--text-secondary)]',
                                        )}
                                    >
                                        {securityNote}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </aside>

                <main
                    className={cn(
                        'relative flex h-svh items-center justify-center overflow-hidden',
                        isEditorial
                            ? 'bg-background px-4 py-5 sm:px-6 sm:py-6 lg:px-8 xl:px-10'
                            : 'px-4 py-5 sm:px-6 sm:py-6 lg:px-8 xl:px-10',
                    )}
                >
                    <div className="relative z-10 flex w-full max-w-[34rem] flex-col gap-4">
                        <div className={cn(isEditorial ? 'space-y-3 lg:hidden' : 'space-y-4 lg:hidden')}>
                            <Link href={home()} className="inline-flex">
                                <BrandMark name={name} editorial={isEditorial} />
                            </Link>

                            <div className="space-y-2.5">
                                {eyebrow ? (
                                    <div
                                        className={cn(
                                            'inline-flex items-center',
                                            isEditorial
                                                ? 'text-[12px] font-medium tracking-[-0.01em] text-[color:var(--text-secondary)]'
                                                : 'rounded-full border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface)] px-3 py-1 text-[11px] font-semibold tracking-[0.12em] text-[color:var(--text-secondary)] uppercase shadow-[var(--shadow-xs)]',
                                        )}
                                    >
                                        {eyebrow}
                                    </div>
                                ) : null}

                                {panelTitle ? (
                                    <p
                                        className={cn(
                                            'max-w-md text-sm leading-6',
                                            isEditorial
                                                ? 'text-[color:var(--text-secondary)]'
                                                : 'text-[color:var(--text-secondary)]',
                                        )}
                                    >
                                        {panelTitle}
                                    </p>
                                ) : null}
                            </div>
                        </div>

                        <Card
                            className={cn(
                                isEditorial
                                    ? 'rounded-[20px] border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] shadow-[var(--shadow-xs)]'
                                    : 'rounded-[var(--radius-hero)] border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] shadow-[var(--shadow-md)]',
                                cardClassName,
                            )}
                        >
                            <CardHeader
                                className={cn(
                                    'gap-2 px-6 pt-6 sm:px-7 sm:pt-7',
                                    isEditorial && 'gap-3 pb-1',
                                )}
                            >
                                {showCardBrand ? (
                                    <div className="flex items-center gap-2.5 text-[12px] text-[color:var(--text-secondary)]">
                                        <div className="flex size-8 items-center justify-center rounded-[10px] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]">
                                            <AppLogoIcon className="size-4 text-foreground" />
                                        </div>
                                        <span className="font-medium text-foreground">
                                            {name}
                                        </span>
                                    </div>
                                ) : null}
                                <CardTitle
                                    className={cn(
                                        'tracking-[-0.03em]',
                                        isEditorial
                                            ? 'text-[1.55rem] leading-8 text-foreground'
                                            : 'text-[1.75rem] leading-8',
                                    )}
                                >
                                    {title}
                                </CardTitle>
                                {description ? (
                                    <CardDescription
                                        className={cn(
                                            'max-w-sm text-sm leading-6',
                                            isEditorial &&
                                                'text-[13.5px] text-[color:var(--text-secondary)]',
                                        )}
                                    >
                                        {description}
                                    </CardDescription>
                                ) : null}
                            </CardHeader>
                            <CardContent
                                className={cn(
                                    'px-6 pb-6 sm:px-7 sm:pb-7',
                                    isEditorial && 'pt-1',
                                    contentClassName,
                                )}
                            >
                                {children}
                            </CardContent>
                        </Card>

                        {(mobileHighlights.length > 0 || securityNote || helpNote) && (
                            <div className="grid gap-3 lg:hidden">
                                {mobileHighlights.map((highlight) => (
                                    <div
                                        key={highlight.title}
                                        className={cn(
                                            isEditorial
                                                ? 'rounded-[16px] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] p-4 shadow-[var(--shadow-xs)]'
                                                : 'rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface)] p-4 shadow-[var(--shadow-xs)]',
                                        )}
                                    >
                                        <h2
                                            className={cn(
                                                'text-sm font-semibold tracking-[-0.01em]',
                                                isEditorial
                                                    ? 'text-foreground'
                                                    : 'text-foreground',
                                            )}
                                        >
                                            {highlight.title}
                                        </h2>
                                        <p
                                            className={cn(
                                                'mt-1 text-[13px]',
                                                isEditorial
                                                    ? 'leading-5 text-[color:var(--text-secondary)]'
                                                    : 'leading-[1.35rem] text-[color:var(--text-secondary)]',
                                            )}
                                        >
                                            {highlight.description}
                                        </p>
                                    </div>
                                ))}

                                {(securityNote || helpNote) && (
                                    <div
                                        className={cn(
                                            'p-4 text-[13px]',
                                            isEditorial
                                                ? 'rounded-[16px] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] leading-5 text-[color:var(--text-secondary)] shadow-[var(--shadow-xs)]'
                                                : 'rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface)] leading-[1.35rem] text-[color:var(--text-secondary)] shadow-[var(--shadow-xs)]',
                                        )}
                                    >
                                        {securityNote}
                                        {securityNote && helpNote ? ' ' : null}
                                        {helpNote}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </div>
    );
}
