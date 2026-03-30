import * as React from 'react';

import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function KpiStrip({
    className,
    ...props
}: React.ComponentProps<'section'>) {
    return (
        <section
            className={cn('grid gap-4 md:grid-cols-2 xl:grid-cols-4', className)}
            {...props}
        />
    );
}

type MetricTone = 'default' | 'success' | 'warning' | 'danger' | 'info';

const METRIC_TONE_STYLES: Record<MetricTone, string> = {
    default:
        'bg-[color:var(--bg-surface-muted)] text-[color:var(--text-secondary)]',
    success:
        'bg-[var(--status-success-soft)] text-[color:var(--status-success)]',
    warning:
        'bg-[var(--status-warning-soft)] text-[color:var(--status-warning)]',
    danger:
        'bg-[var(--status-danger-soft)] text-[color:var(--status-danger)]',
    info: 'bg-[var(--status-info-soft)] text-[color:var(--status-info)]',
};

type MetricCardProps = {
    label: string;
    value: React.ReactNode;
    description?: React.ReactNode;
    tone?: MetricTone;
    className?: string;
};

export function MetricCard({
    label,
    value,
    description,
    tone = 'default',
    className,
}: MetricCardProps) {
    return (
        <Card className={cn('gap-0 py-0', className)}>
            <CardContent className="px-5 py-4">
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className={cn(
                                'inline-flex h-2.5 w-2.5 rounded-full',
                                METRIC_TONE_STYLES[tone],
                            )}
                        />
                        <p className="text-[11px] font-semibold tracking-[0.08em] text-[color:var(--text-secondary)] uppercase">
                            {label}
                        </p>
                    </div>

                    <div className="space-y-1">
                        <p className="text-2xl font-semibold tracking-[-0.02em] text-foreground">
                            {value}
                        </p>
                        {description && (
                            <p className="text-xs text-[color:var(--text-secondary)]">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
