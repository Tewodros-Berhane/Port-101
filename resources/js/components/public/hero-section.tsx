import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import PublicReveal from '@/components/public/public-reveal';
import { PublicEyebrow, type PublicHref } from '@/components/public/public-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { login } from '@/routes';

type HeroPreviewCard = {
    title: string;
    helper: string;
};

type HeroPreviewItem = {
    title: string;
    meta: string;
    status: string;
};

export default function HeroSection({
    isAuthenticated,
    dashboardHref,
    trustChips,
    previewCards,
    previewQueue,
}: {
    isAuthenticated: boolean;
    dashboardHref: PublicHref;
    trustChips: string[];
    previewCards: HeroPreviewCard[];
    previewQueue: HeroPreviewItem[];
}) {
    return (
        <section className="border-b border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)]">
            <div className="mx-auto w-full max-w-7xl px-6 py-16 lg:px-8 lg:py-24">
                <div className="grid gap-12 lg:grid-cols-[minmax(0,0.92fr)_minmax(16rem,0.48fr)] lg:items-start">
                    <PublicReveal className="space-y-8" y={20}>
                        <PublicEyebrow>Port-101 ERP system</PublicEyebrow>

                        <div className="space-y-5">
                            <h1 className="max-w-5xl text-balance text-4xl font-semibold tracking-[-0.045em] text-foreground sm:text-5xl lg:text-[4.15rem] lg:leading-[0.98]">
                                Keep sales, purchasing, inventory, accounting, projects,
                                HR, approvals, reporting, and integrations in one operating system.
                            </h1>
                            <p className="max-w-2xl text-[15px] leading-7 text-[color:var(--text-secondary)] sm:text-lg">
                                Port-101 keeps day-to-day records, review states, and
                                execution paths inside one ERP system so teams do not
                                lose context between departments.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild size="lg">
                                {isAuthenticated ? (
                                    <Link href={dashboardHref}>
                                        Open dashboard
                                        <ArrowRight className="size-4" />
                                    </Link>
                                ) : (
                                    <Link href="/book-demo">
                                        Book demo
                                        <ArrowRight className="size-4" />
                                    </Link>
                                )}
                            </Button>
                            {!isAuthenticated ? (
                                <Button asChild variant="outline" size="lg">
                                    <Link href="/contact-sales">Contact sales</Link>
                                </Button>
                            ) : (
                                <Button asChild variant="outline" size="lg">
                                    <a href="#modules">View module coverage</a>
                                </Button>
                            )}
                        </div>

                        {!isAuthenticated ? (
                            <p className="text-sm text-[color:var(--text-secondary)]">
                                Already have access?{' '}
                                <Link
                                    href={login()}
                                    className="font-medium text-foreground underline decoration-[color:var(--border-strong)] underline-offset-4 transition-colors hover:text-primary"
                                >
                                    Sign in
                                </Link>
                            </p>
                        ) : null}
                    </PublicReveal>

                    <PublicReveal className="grid gap-6 lg:pl-8 lg:pt-8" delay={0.08} y={24}>
                        <div className="space-y-4 border-l border-[color:var(--border-subtle)] pl-5 lg:pl-6">
                            <p className="text-xs font-semibold tracking-[0.14em] text-[color:var(--text-muted)] uppercase">
                                At a glance
                            </p>
                            <div className="space-y-4">
                                {previewCards.map((card, index) => (
                                    <div key={card.title} className="space-y-1">
                                        <div className="flex items-center gap-3">
                                            <span className="text-sm font-semibold text-foreground">
                                                0{index + 1}
                                            </span>
                                            <p className="text-sm font-semibold text-foreground">
                                                {card.title}
                                            </p>
                                        </div>
                                        <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                                            {card.helper}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <p className="text-sm leading-6 text-[color:var(--text-secondary)]">
                            {trustChips.slice(0, 3).join(' | ')}
                        </p>
                    </PublicReveal>
                </div>

                <PublicReveal className="mt-12 lg:mt-14 lg:ml-[11%]" delay={0.12} y={28}>
                    <Card className="rounded-[var(--radius-hero)] border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-elevated)] py-0 shadow-[var(--shadow-md)]">
                        <CardHeader className="border-b border-[color:var(--border-subtle)] px-6 py-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="text-xs font-medium tracking-[0.14em] text-[color:var(--text-muted)] uppercase">
                                        Product view
                                    </div>
                                    <CardTitle className="text-lg leading-7 tracking-[-0.02em]">
                                        Cross-functional operating model
                                    </CardTitle>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <span className="text-xs font-medium text-[color:var(--text-secondary)]">
                                        Operations
                                    </span>
                                    <span className="text-xs font-medium text-[color:var(--text-secondary)]">
                                        Finance
                                    </span>
                                    <span className="text-xs font-medium text-[color:var(--text-secondary)]">
                                        Controls
                                    </span>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-8 px-6 py-6 lg:grid-cols-[0.9fr_1.1fr]">
                            <div className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                                    {previewCards.map((card) => (
                                        <div
                                            key={card.title}
                                            className="border-l border-[color:var(--border-subtle)] pl-4"
                                        >
                                            <p className="text-sm font-semibold text-foreground">
                                                {card.title}
                                            </p>
                                            <p className="mt-1 text-sm leading-6 text-[color:var(--text-secondary)]">
                                                {card.helper}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-[var(--radius-panel)] bg-card/88 p-4 ring-1 ring-[color:var(--border-subtle)]">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">
                                            Controlled execution queue
                                        </p>
                                        <p className="text-xs text-[color:var(--text-secondary)]">
                                            The same status-driven review pattern used across the app
                                        </p>
                                    </div>
                                    <Badge variant="secondary">Live</Badge>
                                </div>

                                <div className="space-y-3">
                                    {previewQueue.map((item) => (
                                        <div
                                            key={`${item.title}-${item.status}`}
                                            className="grid gap-3 border-t border-[color:var(--border-subtle)] pt-3 first:border-t-0 first:pt-0 sm:grid-cols-[minmax(0,1fr)_auto]"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-foreground">
                                                    {item.title}
                                                </p>
                                                <p className="mt-1 text-xs leading-5 text-[color:var(--text-secondary)]">
                                                    {item.meta}
                                                </p>
                                            </div>
                                            <div className="sm:justify-self-end">
                                                <StatusBadge status={item.status} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </PublicReveal>
            </div>
        </section>
    );
}
