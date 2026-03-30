import * as React from 'react';

import { MetricCard } from '@/components/shell/kpi-strip';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type DetailHeroMetric = {
    label: string;
    value: React.ReactNode;
    description?: React.ReactNode;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
};

type DetailHeroProps = {
    title: string;
    description?: string;
    status?: React.ReactNode;
    meta?: React.ReactNode;
    actions?: React.ReactNode;
    metrics?: DetailHeroMetric[];
    className?: string;
};

export function DetailHero({
    title,
    description,
    status,
    meta,
    actions,
    metrics = [],
    className,
}: DetailHeroProps) {
    return (
        <Card
            className={cn(
                'overflow-hidden rounded-[var(--radius-hero)] border-[color:var(--border-default)] bg-[color:var(--bg-surface-elevated)] py-0 shadow-[var(--shadow-sm)]',
                className,
            )}
        >
            <CardContent className="px-6 py-6 md:px-7">
                <div className="space-y-6">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div className="min-w-0 flex-1 space-y-3">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-2xl font-semibold tracking-[-0.02em] text-foreground md:text-[28px] md:leading-8">
                                    {title}
                                </h1>
                                {status}
                            </div>

                            {description && (
                                <p className="max-w-3xl text-sm leading-6 text-[color:var(--text-secondary)]">
                                    {description}
                                </p>
                            )}

                            {meta && (
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-[color:var(--text-secondary)]">
                                    {meta}
                                </div>
                            )}
                        </div>

                        {actions && (
                            <div className="flex shrink-0 flex-wrap items-center gap-2 xl:justify-end">
                                {actions}
                            </div>
                        )}
                    </div>

                    {metrics.length > 0 && (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {metrics.map((metric) => (
                                <MetricCard
                                    key={metric.label}
                                    label={metric.label}
                                    value={metric.value}
                                    description={metric.description}
                                    tone={metric.tone}
                                    className="bg-transparent shadow-none"
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
