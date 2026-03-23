import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Delivery = {
    id: string;
    webhook_endpoint_id: string;
    endpoint_name?: string | null;
    endpoint_url?: string | null;
    integration_event_id: string;
    event_label: string;
    event_type: string;
    event_payload: Record<string, unknown>;
    occurred_at?: string | null;
    status: string;
    status_label: string;
    attempt_count: number;
    last_attempt_at?: string | null;
    next_retry_at?: string | null;
    response_status?: number | null;
    duration_ms?: number | null;
    response_body_excerpt?: string | null;
    failure_message?: string | null;
    delivered_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    can_retry: boolean;
};

type Props = {
    delivery: Delivery;
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function ShowWebhookDelivery({ delivery }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Integrations', href: '/company/integrations' },
                {
                    title: 'Delivery queue',
                    href: '/company/integrations/deliveries',
                },
                {
                    title: delivery.id,
                    href: `/company/integrations/deliveries/${delivery.id}`,
                },
            ]}
        >
            <Head title={`Delivery ${delivery.id}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Delivery {delivery.id}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {delivery.event_label} to{' '}
                            {delivery.endpoint_name ?? 'Unknown endpoint'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {delivery.endpoint_name && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/company/integrations/webhooks/${delivery.webhook_endpoint_id}`}
                                >
                                    Open endpoint
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/company/integrations/deliveries">
                                Back to queue
                            </Link>
                        </Button>
                        {delivery.can_retry && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/company/integrations/deliveries/${delivery.id}/retry`,
                                    )
                                }
                            >
                                Retry delivery
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <section className="space-y-6">
                        <div className="rounded-xl border p-5">
                            <h2 className="text-sm font-semibold">
                                Event payload
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Exact outbound payload body recorded for this
                                delivery.
                            </p>

                            <pre className="mt-4 overflow-x-auto rounded-xl bg-muted/30 p-4 text-xs leading-6 whitespace-pre-wrap">
                                {JSON.stringify(delivery.event_payload, null, 2)}
                            </pre>
                        </div>

                        <div className="rounded-xl border p-5">
                            <h2 className="text-sm font-semibold">
                                Endpoint response
                            </h2>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <MetricCard
                                    label="HTTP status"
                                    value={
                                        delivery.response_status
                                            ? String(delivery.response_status)
                                            : '-'
                                    }
                                />
                                <MetricCard
                                    label="Duration"
                                    value={
                                        delivery.duration_ms
                                            ? `${delivery.duration_ms} ms`
                                            : '-'
                                    }
                                />
                            </div>

                            <div className="mt-4 space-y-4">
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Failure message
                                    </p>
                                    <div className="mt-2 rounded-xl bg-muted/30 p-4 text-sm">
                                        {delivery.failure_message ?? 'None'}
                                    </div>
                                </div>

                                <div>
                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Response excerpt
                                    </p>
                                    <div className="mt-2 rounded-xl bg-muted/30 p-4 text-sm whitespace-pre-wrap">
                                        {delivery.response_body_excerpt ?? 'No response excerpt stored.'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <aside className="space-y-6">
                        <div className="rounded-xl border p-5">
                            <h2 className="text-sm font-semibold">
                                Delivery details
                            </h2>

                            <dl className="mt-4 space-y-3">
                                <DetailItem label="Status">
                                    <StatusPill
                                        status={delivery.status}
                                        label={delivery.status_label}
                                    />
                                </DetailItem>
                                <DetailItem label="Endpoint">
                                    <div className="space-y-1">
                                        <p>{delivery.endpoint_name ?? '-'}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {delivery.endpoint_url ?? '-'}
                                        </p>
                                    </div>
                                </DetailItem>
                                <DetailItem label="Event">
                                    <div className="space-y-1">
                                        <p>{delivery.event_label}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {delivery.event_type}
                                        </p>
                                    </div>
                                </DetailItem>
                                <DetailItem label="Attempt count">
                                    {String(delivery.attempt_count)}
                                </DetailItem>
                                <DetailItem label="Occurred at">
                                    {formatDateTime(delivery.occurred_at)}
                                </DetailItem>
                                <DetailItem label="Last attempt">
                                    {formatDateTime(delivery.last_attempt_at)}
                                </DetailItem>
                                <DetailItem label="Next retry">
                                    {formatDateTime(delivery.next_retry_at)}
                                </DetailItem>
                                <DetailItem label="Delivered at">
                                    {formatDateTime(delivery.delivered_at)}
                                </DetailItem>
                                <DetailItem label="Created at">
                                    {formatDateTime(delivery.created_at)}
                                </DetailItem>
                                <DetailItem label="Updated at">
                                    {formatDateTime(delivery.updated_at)}
                                </DetailItem>
                            </dl>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border px-4 py-3">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-xl font-semibold">{value}</p>
        </div>
    );
}

function DetailItem({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="rounded-xl bg-muted/20 px-4 py-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 text-sm font-medium">{children}</dd>
        </div>
    );
}

function StatusPill({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    const toneClass =
        status === 'delivered'
            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'
            : status === 'dead'
              ? 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-300'
              : status === 'failed'
                ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-300'
                : 'border-border bg-muted text-muted-foreground';

    return (
        <span
            className={`rounded-full border px-2.5 py-1 text-xs font-medium ${toneClass}`}
        >
            {label}
        </span>
    );
}
