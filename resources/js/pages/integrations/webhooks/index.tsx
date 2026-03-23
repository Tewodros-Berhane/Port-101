import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

type EventOption = {
    value: string;
    label: string;
};

type EndpointRow = {
    id: string;
    name: string;
    target_url: string;
    is_active: boolean;
    subscribed_event_labels: string[];
    secret_preview: string;
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
    last_tested_at?: string | null;
    last_success_at?: string | null;
    last_failure_at?: string | null;
    can_view: boolean;
    can_edit: boolean;
    can_test: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    filters: {
        search: string;
        event: string;
        is_active: string;
    };
    eventOptions: EventOption[];
    endpoints: {
        data: EndpointRow[];
        links: PaginationLink[];
    };
    abilities: {
        can_create: boolean;
        can_view_deliveries: boolean;
    };
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function WebhookEndpointsIndex({
    filters,
    eventOptions,
    endpoints,
    abilities,
}: Props) {
    const form = useForm({
        search: filters.search,
        event: filters.event,
        is_active: filters.is_active,
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
            ]}
        >
            <Head title="Webhook Endpoints" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Webhook endpoints
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Register outbound endpoints and track their delivery
                            health.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {abilities.can_view_deliveries && (
                            <Button variant="outline" asChild>
                                <Link href="/company/integrations/deliveries">
                                    Delivery queue
                                </Link>
                            </Button>
                        )}
                        {abilities.can_create && (
                            <Button asChild>
                                <Link href="/company/integrations/webhooks/create">
                                    Add endpoint
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/integrations/webhooks', {
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
                            placeholder="Endpoint name or URL"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="event">Subscribed event</Label>
                        <select
                            id="event"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.event}
                            onChange={(event) =>
                                form.setData('event', event.target.value)
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
                        <Label htmlFor="is_active">State</Label>
                        <select
                            id="is_active"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.value)
                            }
                        >
                            <option value="">All states</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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
                                    event: '',
                                    is_active: '',
                                };

                                form.setData(reset);
                                form.get('/company/integrations/webhooks', {
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
                    <table className="w-full min-w-[1320px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Endpoint
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Subscriptions
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Secret preview
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Deliveries
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Latest delivery
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Health
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {endpoints.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No webhook endpoints match the current
                                        filters.
                                    </td>
                                </tr>
                            )}

                            {endpoints.data.map((endpoint) => (
                                <tr key={endpoint.id}>
                                    <td className="px-4 py-3">
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-medium">
                                                    {endpoint.name}
                                                </p>
                                                <StatusBadge
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
                                            </div>
                                            <p className="max-w-[320px] truncate text-xs text-muted-foreground">
                                                {endpoint.target_url}
                                            </p>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="max-w-[260px] space-y-1">
                                            {endpoint.subscribed_event_labels.map(
                                                (label) => (
                                                    <p
                                                        key={`${endpoint.id}-${label}`}
                                                        className="truncate text-xs text-muted-foreground"
                                                    >
                                                        {label}
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {endpoint.secret_preview}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="grid gap-2 md:grid-cols-2">
                                            <MiniStat
                                                label="Total"
                                                value={String(
                                                    endpoint.deliveries_count,
                                                )}
                                            />
                                            <MiniStat
                                                label="Delivered"
                                                value={String(
                                                    endpoint.delivered_deliveries_count,
                                                )}
                                            />
                                            <MiniStat
                                                label="Failed"
                                                value={String(
                                                    endpoint.failed_deliveries_count,
                                                )}
                                            />
                                            <MiniStat
                                                label="Dead"
                                                value={String(
                                                    endpoint.dead_deliveries_count,
                                                )}
                                            />
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {endpoint.latest_delivery ? (
                                            <div className="space-y-1">
                                                <p className="font-medium">
                                                    {
                                                        endpoint.latest_delivery
                                                            .event_label
                                                    }
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {
                                                        endpoint.latest_delivery
                                                            .status_label
                                                    }
                                                    {endpoint.latest_delivery.response_status
                                                        ? ` · HTTP ${endpoint.latest_delivery.response_status}`
                                                        : ''}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        endpoint.latest_delivery
                                                            .delivered_at ??
                                                            endpoint
                                                                .latest_delivery
                                                                .created_at,
                                                    )}
                                                </p>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                No deliveries yet
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        <div className="space-y-1">
                                            <p>
                                                Last success:{' '}
                                                {formatDateTime(
                                                    endpoint.last_success_at,
                                                )}
                                            </p>
                                            <p>
                                                Last failure:{' '}
                                                {formatDateTime(
                                                    endpoint.last_failure_at,
                                                )}
                                            </p>
                                            <p>
                                                Last tested:{' '}
                                                {formatDateTime(
                                                    endpoint.last_tested_at,
                                                )}
                                            </p>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="inline-flex flex-wrap items-center justify-end gap-3">
                                            {endpoint.can_test && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() =>
                                                        router.post(
                                                            `/company/integrations/webhooks/${endpoint.id}/test`,
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Test
                                                </button>
                                            )}
                                            {endpoint.can_edit && (
                                                <Link
                                                    href={`/company/integrations/webhooks/${endpoint.id}/edit`}
                                                    className="font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                            {endpoint.can_view && (
                                                <Link
                                                    href={`/company/integrations/webhooks/${endpoint.id}`}
                                                    className="font-medium text-primary"
                                                >
                                                    Open
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {endpoints.links.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {endpoints.links.map((link) => (
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

function StatusBadge({
    label,
    tone,
}: {
    label: string;
    tone: 'success' | 'muted';
}) {
    const toneClass = {
        success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
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

function MiniStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-muted/30 px-2.5 py-2">
            <p className="text-[11px] text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}
