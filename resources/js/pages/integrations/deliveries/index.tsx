import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

type Option = {
    value: string;
    label: string;
};

type EndpointOption = {
    id: string;
    name: string;
};

type DeliveryRow = {
    id: string;
    webhook_endpoint_id: string;
    endpoint_name?: string | null;
    event_label: string;
    event_type: string;
    status_label: string;
    status: string;
    attempt_count: number;
    response_status?: number | null;
    duration_ms?: number | null;
    failure_message?: string | null;
    next_retry_at?: string | null;
    delivered_at?: string | null;
    created_at?: string | null;
    can_retry: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    filters: {
        search: string;
        status: string;
        event_type: string;
        endpoint_id: string;
    };
    summary: {
        total: number;
        delivered: number;
        failed: number;
        dead: number;
        pending: number;
    };
    statusOptions: Option[];
    eventOptions: Option[];
    endpointOptions: EndpointOption[];
    deliveries: {
        data: DeliveryRow[];
        links: PaginationLink[];
    };
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function WebhookDeliveriesIndex({
    filters,
    summary,
    statusOptions,
    eventOptions,
    endpointOptions,
    deliveries,
}: Props) {
    const form = useForm({
        search: filters.search,
        status: filters.status,
        event_type: filters.event_type,
        endpoint_id: filters.endpoint_id,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Integrations', href: '/company/integrations' },
                {
                    title: 'Delivery queue',
                    href: '/company/integrations/deliveries',
                },
            ]}
        >
            <Head title="Webhook Delivery Queue" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Webhook delivery queue
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Review delivery outcomes across all endpoints and
                            retry dead-letter traffic.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/company/integrations">
                                Integrations dashboard
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/company/integrations/webhooks">
                                Webhook endpoints
                            </Link>
                        </Button>
                    </div>
                </div>

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

                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/integrations/deliveries', {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                            placeholder="Endpoint or event"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        >
                            <option value="">All statuses</option>
                            {statusOptions.map((statusOption) => (
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
                            value={form.data.event_type}
                            onChange={(event) =>
                                form.setData('event_type', event.target.value)
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

                    <div className="grid gap-2">
                        <Label htmlFor="endpoint_id">Endpoint</Label>
                        <select
                            id="endpoint_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.endpoint_id}
                            onChange={(event) =>
                                form.setData('endpoint_id', event.target.value)
                            }
                        >
                            <option value="">All endpoints</option>
                            {endpointOptions.map((endpointOption) => (
                                <option
                                    key={endpointOption.id}
                                    value={endpointOption.id}
                                >
                                    {endpointOption.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <Button type="submit">Apply filters</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const reset = {
                                    search: '',
                                    status: '',
                                    event_type: '',
                                    endpoint_id: '',
                                };

                                form.setData(reset);
                                form.get('/company/integrations/deliveries', {
                                    data: reset,
                                    preserveState: true,
                                    replace: true,
                                });
                            }}
                        >
                            Reset
                        </Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[1280px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Delivery
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Endpoint
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
                                        colSpan={8}
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
                                        {delivery.endpoint_name ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusPill
                                            status={delivery.status}
                                            label={delivery.status_label}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        Attempt {delivery.attempt_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="space-y-1">
                                            <p>
                                                {delivery.response_status
                                                    ? `HTTP ${delivery.response_status}`
                                                    : '-'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {delivery.duration_ms
                                                    ? `${delivery.duration_ms} ms`
                                                    : '-'}
                                            </p>
                                        </div>
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
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
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
                    <div className="flex flex-wrap gap-2">
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
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
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
