import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

type EventOption = {
    value: string;
    label: string;
};

type DeliveryStatusOption = EventOption;

type Endpoint = {
    id: string;
    name: string;
    target_url: string;
    api_version: string;
    is_active: boolean;
    subscribed_event_labels: string[];
    secret_preview: string;
    revealed_signing_secret?: string | null;
    deliveries_count: number;
    delivered_deliveries_count: number;
    failed_deliveries_count: number;
    dead_deliveries_count: number;
    last_tested_at?: string | null;
    last_success_at?: string | null;
    last_failure_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    can_edit: boolean;
    can_delete: boolean;
    can_rotate_secret: boolean;
    can_test: boolean;
};

type DeliveryRow = {
    id: string;
    event_label: string;
    event_type: string;
    status_label: string;
    status: string;
    attempt_count: number;
    response_status?: number | null;
    failure_message?: string | null;
    delivered_at?: string | null;
    next_retry_at?: string | null;
    created_at?: string | null;
    can_retry: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    endpoint: Endpoint;
    filters: {
        status: string;
        event_type: string;
    };
    summary: {
        total: number;
        delivered: number;
        failed: number;
        dead: number;
        pending: number;
    };
    deliveryStatusOptions: DeliveryStatusOption[];
    eventOptions: EventOption[];
    deliveries: {
        data: DeliveryRow[];
        links: PaginationLink[];
    };
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function ShowWebhookEndpoint({
    endpoint,
    filters,
    summary,
    deliveryStatusOptions,
    eventOptions,
    deliveries,
}: Props) {
    const filterForm = useForm({
        status: filters.status,
        event_type: filters.event_type,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Integrations', href: '/company/integrations' },
                {
                    title: 'Webhook endpoints',
                    href: '/company/integrations/webhooks',
                },
                {
                    title: endpoint.name,
                    href: `/company/integrations/webhooks/${endpoint.id}`,
                },
            ]}
        >
            <Head title={endpoint.name} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {endpoint.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {endpoint.target_url}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/company/integrations/webhooks">
                                Back to endpoints
                            </Link>
                        </Button>
                        {endpoint.can_edit && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/company/integrations/webhooks/${endpoint.id}/edit`}
                                >
                                    Edit endpoint
                                </Link>
                            </Button>
                        )}
                        {endpoint.can_test && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/company/integrations/webhooks/${endpoint.id}/test`,
                                    )
                                }
                            >
                                Send test
                            </Button>
                        )}
                        {endpoint.can_rotate_secret && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/company/integrations/webhooks/${endpoint.id}/rotate-secret`,
                                    )
                                }
                            >
                                Rotate secret
                            </Button>
                        )}
                        {endpoint.can_delete && (
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (
                                        !window.confirm(
                                            'Remove this webhook endpoint and its delivery history?',
                                        )
                                    ) {
                                        return;
                                    }

                                    router.delete(
                                        `/company/integrations/webhooks/${endpoint.id}`,
                                    );
                                }}
                            >
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                {endpoint.revealed_signing_secret && (
                    <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                        <p className="text-sm font-semibold">
                            New signing secret
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Copy this now. It is only revealed once after
                            creation or rotation.
                        </p>
                        <div className="mt-3 rounded-lg border bg-background px-3 py-2 font-mono text-sm">
                            {endpoint.revealed_signing_secret}
                        </div>
                    </div>
                )}

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <MetricCard label="Total" value={String(summary.total)} />
                    <MetricCard
                        label="Delivered"
                        value={String(summary.delivered)}
                    />
                    <MetricCard
                        label="Retry scheduled"
                        value={String(summary.failed)}
                    />
                    <MetricCard
                        label="Dead letters"
                        value={String(summary.dead)}
                    />
                    <MetricCard
                        label="In flight"
                        value={String(summary.pending)}
                    />
                </section>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section className="rounded-xl border p-5">
                        <h2 className="text-sm font-semibold">
                            Endpoint details
                        </h2>

                        <dl className="mt-4 grid gap-4 md:grid-cols-2">
                            <DetailItem label="State">
                                {endpoint.is_active ? 'Active' : 'Inactive'}
                            </DetailItem>
                            <DetailItem label="API version">
                                {endpoint.api_version}
                            </DetailItem>
                            <DetailItem label="Secret preview">
                                <span className="font-mono text-xs">
                                    {endpoint.secret_preview}
                                </span>
                            </DetailItem>
                            <DetailItem label="Created">
                                {formatDateTime(endpoint.created_at)}
                            </DetailItem>
                            <DetailItem label="Last tested">
                                {formatDateTime(endpoint.last_tested_at)}
                            </DetailItem>
                            <DetailItem label="Last success">
                                {formatDateTime(endpoint.last_success_at)}
                            </DetailItem>
                            <DetailItem label="Last failure">
                                {formatDateTime(endpoint.last_failure_at)}
                            </DetailItem>
                            <DetailItem label="Updated">
                                {formatDateTime(endpoint.updated_at)}
                            </DetailItem>
                        </dl>
                    </section>

                    <section className="rounded-xl border p-5">
                        <h2 className="text-sm font-semibold">
                            Subscribed events
                        </h2>
                        <div className="mt-4 space-y-2">
                            {endpoint.subscribed_event_labels.map((label) => (
                                <div
                                    key={label}
                                    className="rounded-lg border bg-muted/20 px-3 py-2 text-sm"
                                >
                                    {label}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <section className="rounded-xl border p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Delivery history
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Filter recent attempts for this endpoint and
                                retry failed or dead-letter records.
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href="/company/integrations/deliveries">
                                Open global queue
                            </Link>
                        </Button>
                    </div>

                    <form
                        className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            filterForm.get(
                                `/company/integrations/webhooks/${endpoint.id}`,
                                {
                                    preserveState: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                                value={filterForm.data.status}
                                onChange={(event) =>
                                    filterForm.setData(
                                        'status',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">All statuses</option>
                                {deliveryStatusOptions.map((statusOption) => (
                                    <option
                                        key={statusOption.value}
                                        value={statusOption.value}
                                    >
                                        {statusOption.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="event_type">Event</Label>
                            <select
                                id="event_type"
                                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                                value={filterForm.data.event_type}
                                onChange={(event) =>
                                    filterForm.setData(
                                        'event_type',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">All events</option>
                                {eventOptions.map((eventOption) => (
                                    <option
                                        key={eventOption.value}
                                        value={eventOption.value}
                                    >
                                        {eventOption.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-2">
                            <Button type="submit">Apply filters</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    const reset = {
                                        status: '',
                                        event_type: '',
                                    };

                                    filterForm.setData(reset);
                                    filterForm.get(
                                        `/company/integrations/webhooks/${endpoint.id}`,
                                        {
                                            data: reset,
                                            preserveState: true,
                                            replace: true,
                                        },
                                    );
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </form>

                    <div className="mt-4 overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-[1100px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Event
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Attempt
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Response
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Failure
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Timing
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {deliveries.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No deliveries match the current
                                            filters.
                                        </td>
                                    </tr>
                                )}

                                {deliveries.data.map((delivery) => (
                                    <tr key={delivery.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {delivery.event_label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {delivery.event_type}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusCell
                                                label={delivery.status_label}
                                                status={delivery.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            Attempt {delivery.attempt_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            {delivery.response_status
                                                ? `HTTP ${delivery.response_status}`
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="max-w-[260px] truncate text-muted-foreground">
                                                {delivery.failure_message ?? '-'}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            <div className="space-y-1">
                                                <p>
                                                    Created{' '}
                                                    {formatDateTime(
                                                        delivery.created_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Delivered{' '}
                                                    {formatDateTime(
                                                        delivery.delivered_at,
                                                    )}
                                                </p>
                                                <p>
                                                    Next retry{' '}
                                                    {formatDateTime(
                                                        delivery.next_retry_at,
                                                    )}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex flex-wrap items-center justify-end gap-3">
                                                {delivery.can_retry && (
                                                    <button
                                                        type="button"
                                                        className="font-medium text-primary"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/integrations/deliveries/${delivery.id}/retry`,
                                                            )
                                                        }
                                                    >
                                                        Retry
                                                    </button>
                                                )}
                                                <Link
                                                    href={`/company/integrations/deliveries/${delivery.id}`}
                                                    className="font-medium text-primary"
                                                >
                                                    Open
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {deliveries.links.length > 1 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {deliveries.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'border-primary text-primary'
                                            : 'text-muted-foreground'
                                    } ${
                                        !link.url
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
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
        <div className="rounded-lg bg-muted/20 px-4 py-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 text-sm font-medium">{children}</dd>
        </div>
    );
}

function StatusCell({
    label,
    status,
}: {
    label: string;
    status: string;
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
