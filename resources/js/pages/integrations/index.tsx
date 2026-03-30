import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type EndpointRow = {
    id: string;
    name: string;
    target_url: string;
    is_active: boolean;
    health_status: string;
    secret_rotated_at?: string | null;
    deliveries_count: number;
    delivered_deliveries_count: number;
    failed_deliveries_count: number;
    dead_deliveries_count: number;
    latest_delivery?: {
        id: string;
        event_label: string;
        status_label: string;
        response_status?: number | null;
        delivered_at?: string | null;
        created_at?: string | null;
    } | null;
    can_view: boolean;
};

type DeliveryRow = {
    id: string;
    endpoint_name?: string | null;
    event_label: string;
    status_label: string;
    status: string;
    response_status?: number | null;
    attempt_count: number;
    next_retry_at?: string | null;
    failure_message?: string | null;
    created_at?: string | null;
    can_retry: boolean;
};

type EventActivityRow = {
    event_type: string;
    event_label: string;
    count: number;
};

type Props = {
    summary: {
        total_endpoints: number;
        active_endpoints: number;
        failing_endpoints: number;
        delivered_last_7_days: number;
        dead_letters: number;
        pending_retries: number;
        success_rate_last_7_days?: number | null;
        average_duration_ms_last_7_days?: number | null;
    };
    recentEndpoints: EndpointRow[];
    deadLetters: DeliveryRow[];
    recentEventActivity: EventActivityRow[];
    abilities: {
        can_manage_webhooks: boolean;
        can_view_deliveries: boolean;
    };
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function IntegrationsDashboard({
    summary,
    recentEndpoints,
    deadLetters,
    recentEventActivity,
    abilities,
}: Props) {
    const maxEventCount = Math.max(
        1,
        ...recentEventActivity.map((eventActivity) => eventActivity.count),
    );

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.integrations, )}
        >
            <Head title="Integrations" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Integrations workspace
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage webhook endpoints, watch delivery health, and
                            recover dead-letter traffic from one place.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/company/integrations/webhooks">
                                Webhook endpoints
                            </Link>
                        </Button>
                        {abilities.can_view_deliveries && (
                            <Button variant="outline" asChild>
                                <Link href="/company/integrations/deliveries">
                                    Delivery queue
                                </Link>
                            </Button>
                        )}
                        {abilities.can_manage_webhooks && (
                            <Button asChild>
                                <Link href="/company/integrations/webhooks/create">
                                    Add endpoint
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <MetricCard
                        label="Total endpoints"
                        value={String(summary.total_endpoints)}
                        description={`${summary.active_endpoints} active`}
                    />
                    <MetricCard
                        label="Failing endpoints"
                        value={String(summary.failing_endpoints)}
                        description="Endpoints with a newer failure than success"
                    />
                    <MetricCard
                        label="Deliveries last 7 days"
                        value={String(summary.delivered_last_7_days)}
                        description={`${summary.pending_retries} retries pending`}
                    />
                    <MetricCard
                        label="Dead letters"
                        value={String(summary.dead_letters)}
                        description="Manual attention required"
                    />
                    <MetricCard
                        label="Pending retries"
                        value={String(summary.pending_retries)}
                        description="Scheduled retry attempts"
                    />
                    <MetricCard
                        label="Active coverage"
                        value={`${summary.active_endpoints}/${summary.total_endpoints}`}
                        description="Endpoints currently enabled"
                    />
                    <MetricCard
                        label="Success rate"
                        value={
                            summary.success_rate_last_7_days !== null &&
                            summary.success_rate_last_7_days !== undefined
                                ? `${summary.success_rate_last_7_days}%`
                                : '-'
                        }
                        description="Successful deliveries over the last 7 days"
                    />
                    <MetricCard
                        label="Average latency"
                        value={
                            summary.average_duration_ms_last_7_days !== null &&
                            summary.average_duration_ms_last_7_days !==
                                undefined
                                ? `${summary.average_duration_ms_last_7_days} ms`
                                : '-'
                        }
                        description="Average delivery duration over the last 7 days"
                    />
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.9fr)]">
                    <section className="rounded-xl border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Recent endpoints
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Latest configuration and health changes.
                                </p>
                            </div>
                            <Button variant="outline" asChild>
                                <Link href="/company/integrations/webhooks">
                                    View all
                                </Link>
                            </Button>
                        </div>

                        <div className="mt-4 space-y-3">
                            {recentEndpoints.length === 0 && (
                                <EmptyState message="No webhook endpoints yet." />
                            )}

                            {recentEndpoints.map((endpoint) => (
                                <div
                                    key={endpoint.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">
                                                    {endpoint.name}
                                                </p>
                                                <StatusPill
                                                    label={
                                                        endpoint.is_active
                                                            ? 'Active'
                                                            : 'Inactive'
                                                    }
                                                    tone={
                                                        endpoint.is_active
                                                            ? 'success'
                                                            : 'muted'
                                                    }
                                                />
                                                <StatusPill
                                                    label={healthLabel(
                                                        endpoint.health_status,
                                                    )}
                                                    tone={healthTone(
                                                        endpoint.health_status,
                                                    )}
                                                />
                                            </div>
                                            <p className="max-w-[560px] truncate text-xs text-muted-foreground">
                                                {endpoint.target_url}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Secret rotated{' '}
                                                {formatDateTime(
                                                    endpoint.secret_rotated_at,
                                                )}
                                            </p>
                                        </div>
                                        {endpoint.can_view && (
                                            <Button variant="ghost" asChild>
                                                <Link
                                                    href={`/company/integrations/webhooks/${endpoint.id}`}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                        )}
                                    </div>

                                    <div className="mt-4 grid gap-3 md:grid-cols-4">
                                        <MiniMetric
                                            label="Deliveries"
                                            value={String(
                                                endpoint.deliveries_count,
                                            )}
                                        />
                                        <MiniMetric
                                            label="Delivered"
                                            value={String(
                                                endpoint.delivered_deliveries_count,
                                            )}
                                        />
                                        <MiniMetric
                                            label="Failed"
                                            value={String(
                                                endpoint.failed_deliveries_count,
                                            )}
                                        />
                                        <MiniMetric
                                            label="Dead"
                                            value={String(
                                                endpoint.dead_deliveries_count,
                                            )}
                                        />
                                    </div>

                                    {endpoint.latest_delivery && (
                                        <div className="mt-4 rounded-xl bg-muted/30 p-3 text-xs">
                                            <p className="font-medium">
                                                Latest delivery
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                {endpoint.latest_delivery.event_label}{' '}
                                                ·{' '}
                                                {
                                                    endpoint.latest_delivery
                                                        .status_label
                                                }
                                                {endpoint.latest_delivery.response_status
                                                    ? ` · HTTP ${endpoint.latest_delivery.response_status}`
                                                    : ''}
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                {formatDateTime(
                                                    endpoint.latest_delivery
                                                        .delivered_at ??
                                                        endpoint.latest_delivery
                                                            .created_at,
                                                )}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-6">
                        <div className="rounded-xl border p-5">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold">
                                        Dead-letter queue
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Failed deliveries that need a manual
                                        retry or endpoint fix.
                                    </p>
                                </div>
                                {abilities.can_view_deliveries && (
                                    <Button variant="outline" asChild>
                                        <Link href="/company/integrations/deliveries?status=dead">
                                            Open queue
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            <div className="mt-4 space-y-3">
                                {deadLetters.length === 0 && (
                                    <EmptyState message="No failed or dead-letter deliveries right now." />
                                )}

                                {deadLetters.map((delivery) => (
                                    <div
                                        key={delivery.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">
                                                {delivery.event_label}
                                            </p>
                                            <StatusPill
                                                label={delivery.status_label}
                                                tone={
                                                    delivery.status === 'dead'
                                                        ? 'danger'
                                                        : 'warning'
                                                }
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {delivery.endpoint_name ?? '-'} ·
                                            Attempt {delivery.attempt_count}
                                        </p>
                                        {delivery.failure_message && (
                                            <p className="mt-3 line-clamp-2 text-sm text-muted-foreground">
                                                {delivery.failure_message}
                                            </p>
                                        )}
                                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                            <span>
                                                {formatDateTime(
                                                    delivery.created_at,
                                                )}
                                            </span>
                                            <span>
                                                {delivery.next_retry_at
                                                    ? `Next retry ${formatDateTime(delivery.next_retry_at)}`
                                                    : 'No automatic retry left'}
                                            </span>
                                        </div>
                                        <div className="mt-3">
                                            <Button variant="ghost" asChild>
                                                <Link
                                                    href={`/company/integrations/deliveries/${delivery.id}`}
                                                >
                                                    Review delivery
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border p-5">
                            <h2 className="text-sm font-semibold">
                                Event activity
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Outbound event volume for the last 7 days.
                            </p>

                            <div className="mt-4 space-y-3">
                                {recentEventActivity.length === 0 && (
                                    <EmptyState message="No outbound events recorded in the last 7 days." />
                                )}

                                {recentEventActivity.map((eventActivity) => (
                                    <div key={eventActivity.event_type}>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span>{eventActivity.event_label}</span>
                                            <span className="font-medium">
                                                {eventActivity.count}
                                            </span>
                                        </div>
                                        <div className="mt-2 h-2 rounded-full bg-muted">
                                            <div
                                                className="h-2 rounded-full bg-primary"
                                                style={{
                                                    width: `${(eventActivity.count / maxEventCount) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

function MetricCard({
    label,
    value,
    description,
}: {
    label: string;
    value: string;
    description: string;
}) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
            <p className="mt-2 text-xs text-muted-foreground">{description}</p>
        </div>
    );
}

function MiniMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-muted/30 px-3 py-2">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}

function StatusPill({
    label,
    tone,
}: {
    label: string;
    tone: 'success' | 'warning' | 'danger' | 'muted';
}) {
    const toneClass = {
        success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
        warning: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-300',
        danger: 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-300',
        muted: 'border-border bg-muted text-muted-foreground',
    }[tone];

    return (
        <span
            className={`rounded-full border px-2.5 py-1 text-xs font-medium ${toneClass}`}
        >
            {label}
        </span>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
            {message}
        </div>
    );
}

function healthTone(status: string): 'success' | 'warning' | 'danger' | 'muted' {
    if (status === 'healthy') {
        return 'success';
    }

    if (status === 'warning' || status === 'degraded') {
        return 'warning';
    }

    if (status === 'inactive') {
        return 'muted';
    }

    return 'danger';
}

function healthLabel(status: string): string {
    return {
        healthy: 'Healthy',
        warning: 'Warning',
        degraded: 'Degraded',
        inactive: 'Inactive',
    }[status] ?? status;
}
