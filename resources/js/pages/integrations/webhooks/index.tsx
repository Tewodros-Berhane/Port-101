import { Head, Link, router, useForm } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { DataTableShell } from '@/components/shell/data-table-shell';
import {
    FilterField,
    FilterToolbar,
    FilterToolbarActions,
    FilterToolbarGrid,
} from '@/components/shell/filter-toolbar';
import { PageHeader } from '@/components/shell/page-header';
import { PaginationBar } from '@/components/shell/pagination-bar';
import { WorkspaceShell } from '@/components/shell/workspace-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

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
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.integrations, {
                    title: 'Webhook endpoints',
                    href: '/company/integrations/webhooks',
                },)}
        >
            <Head title="Webhook Endpoints" />

            <WorkspaceShell
                header={
                    <PageHeader
                        title="Webhook endpoints"
                        description="Register outbound endpoints and track their delivery health."
                        actions={
                            <>
                                <BackLinkAction href="/company/integrations" label="Back to integrations
                                    " variant="outline" />
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
                            </>
                        }
                    />
                }
                table={
                    <DataTableShell>
                        <Table container={false} className="min-w-[1320px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Endpoint</TableHead>
                                    <TableHead>Subscriptions</TableHead>
                                    <TableHead>Secret preview</TableHead>
                                    <TableHead>Deliveries</TableHead>
                                    <TableHead>Latest delivery</TableHead>
                                    <TableHead>Health</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {endpoints.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-12 text-center text-sm text-muted-foreground"
                                        >
                                            No webhook endpoints match the
                                            current filters.
                                        </TableCell>
                                    </TableRow>
                                )}

                                {endpoints.data.map((endpoint) => (
                                    <TableRow key={endpoint.id}>
                                        <TableCell>
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium">
                                                        {endpoint.name}
                                                    </p>
                                                    <StatusBadge
                                                        status={
                                                            endpoint.is_active
                                                                ? 'active'
                                                                : 'inactive'
                                                        }
                                                    />
                                                </div>
                                                <p className="max-w-[320px] truncate text-xs text-muted-foreground">
                                                    {endpoint.target_url}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
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
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {endpoint.secret_preview}
                                        </TableCell>
                                        <TableCell>
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
                                        </TableCell>
                                        <TableCell>
                                            {endpoint.latest_delivery ? (
                                                <div className="space-y-1">
                                                    <p className="font-medium">
                                                        {
                                                            endpoint
                                                                .latest_delivery
                                                                .event_label
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            endpoint
                                                                .latest_delivery
                                                                .status_label
                                                        }
                                                        {endpoint
                                                            .latest_delivery
                                                            .response_status
                                                            ? ` � HTTP ${endpoint.latest_delivery.response_status}`
                                                            : ''}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDateTime(
                                                            endpoint
                                                                .latest_delivery
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
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
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
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex flex-wrap items-center justify-end gap-1">
                                                {endpoint.can_test && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
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
                                                    </Button>
                                                )}
                                                {endpoint.can_edit && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/company/integrations/webhooks/${endpoint.id}/edit`}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                )}
                                                {endpoint.can_view && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/company/integrations/webhooks/${endpoint.id}`}
                                                        >
                                                            Open
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </DataTableShell>
                }
                pagination={<PaginationBar links={endpoints.links} />}
            >
                <FilterToolbar
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/company/integrations/webhooks', form.data, {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <FilterToolbarGrid className="xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,0.9fr)_auto]">
                        <FilterField label="Search" htmlFor="search">
                            <Input
                                id="search"
                                value={form.data.search}
                                onChange={(event) =>
                                    form.setData('search', event.target.value)
                                }
                                placeholder="Endpoint name or URL"
                            />
                        </FilterField>

                        <FilterField
                            label="Subscribed event"
                            htmlFor="event"
                        >
                            <select
                                id="event"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
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
                        </FilterField>

                        <FilterField label="State" htmlFor="is_active">
                            <select
                                id="is_active"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.is_active}
                                onChange={(event) =>
                                    form.setData('is_active', event.target.value)
                                }
                            >
                                <option value="">All states</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </FilterField>

                        <FilterToolbarActions>
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
                                    router.get(
                                        '/company/integrations/webhooks',
                                        reset,
                                        {
                                            preserveState: true,
                                            replace: true,
                                        },
                                    );
                                }}
                            >
                                Reset
                            </Button>
                        </FilterToolbarActions>
                    </FilterToolbarGrid>
                </FilterToolbar>
            </WorkspaceShell>
        </AppLayout>
    );
}

function MiniStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[calc(var(--radius-control)+2px)] border border-[color:var(--border-subtle)] bg-muted/35 px-2.5 py-2">
            <p className="text-[11px] text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}
