import { Head, Link, router, useForm } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { DataTableShell } from '@/components/shell/data-table-shell';
import {
    FilterField,
    FilterToolbar,
    FilterToolbarActions,
    FilterToolbarGrid,
} from '@/components/shell/filter-toolbar';
import { KpiStrip, MetricCard } from '@/components/shell/kpi-strip';
import { PageHeader } from '@/components/shell/page-header';
import { PaginationBar } from '@/components/shell/pagination-bar';
import { WorkspaceShell } from '@/components/shell/workspace-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

type Option = {
    value: string;
    label: string;
};

type EndpointOption = {
    id: string;
    name: string;
};

type SecurityPolicy = {
    signature_version: string;
    signature_algorithm: string;
    signed_content: string;
    replay_window_seconds: number;
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
    first_attempt_at?: string | null;
    response_status?: number | null;
    duration_ms?: number | null;
    failure_message?: string | null;
    next_retry_at?: string | null;
    delivered_at?: string | null;
    dead_lettered_at?: string | null;
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
    securityPolicy: SecurityPolicy;
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
    securityPolicy,
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
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.integrations, {
                    title: 'Delivery queue',
                    href: '/company/integrations/deliveries',
                },)}
        >
            <Head title="Webhook Delivery Queue" />

            <WorkspaceShell
                header={
                    <PageHeader
                        title="Webhook delivery queue"
                        description="Review delivery outcomes across all endpoints and retry dead-letter traffic."
                        actions={
                            <>
                                <BackLinkAction href="/company/integrations" label="Back to integrations
                                    " variant="outline" />
                                <Button variant="outline" asChild>
                                    <Link href="/company/integrations/webhooks">
                                        Webhook endpoints
                                    </Link>
                                </Button>
                            </>
                        }
                    />
                }
                kpis={
                    <KpiStrip className="xl:grid-cols-5">
                        <MetricCard label="Total" value={String(summary.total)} />
                        <MetricCard
                            label="Delivered"
                            value={String(summary.delivered)}
                            tone="success"
                        />
                        <MetricCard
                            label="Retry scheduled"
                            value={String(summary.failed)}
                            tone="warning"
                        />
                        <MetricCard
                            label="Dead letters"
                            value={String(summary.dead)}
                            tone="danger"
                        />
                        <MetricCard
                            label="In flight"
                            value={String(summary.pending)}
                            tone="info"
                        />
                    </KpiStrip>
                }
                table={
                    <DataTableShell>
                        <Table container={false} className="min-w-[1280px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Delivery</TableHead>
                                    <TableHead>Endpoint</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Attempt</TableHead>
                                    <TableHead>Response</TableHead>
                                    <TableHead>Failure</TableHead>
                                    <TableHead>Timing</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {deliveries.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="py-12 text-center text-sm text-muted-foreground"
                                        >
                                            No deliveries match the current
                                            filters.
                                        </TableCell>
                                    </TableRow>
                                )}

                                {deliveries.data.map((delivery) => (
                                    <TableRow key={delivery.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {delivery.event_label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {delivery.event_type}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {delivery.endpoint_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={delivery.status}
                                                label={delivery.status_label}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            Attempt {delivery.attempt_count}
                                        </TableCell>
                                        <TableCell>
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
                                        </TableCell>
                                        <TableCell>
                                            <p className="max-w-[260px] truncate text-muted-foreground">
                                                {delivery.failure_message ??
                                                    '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            <div className="space-y-1">
                                                <p>
                                                    First attempt{' '}
                                                    {formatDateTime(
                                                        delivery.first_attempt_at,
                                                    )}
                                                </p>
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
                                                <p>
                                                    Dead letter{' '}
                                                    {formatDateTime(
                                                        delivery.dead_lettered_at,
                                                    )}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex flex-wrap items-center justify-end gap-1">
                                                {delivery.can_retry && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
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
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/company/integrations/deliveries/${delivery.id}`}
                                                    >
                                                        Open
                                                    </Link>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </DataTableShell>
                }
                pagination={<PaginationBar links={deliveries.links} />}
            >
                <Card className="gap-0 py-0">
                    <CardContent className="px-5 py-4 text-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="font-medium">Delivery policy</p>
                                <p className="text-xs text-muted-foreground">
                                    Consumers should verify{' '}
                                    <code>
                                        {securityPolicy.signature_algorithm}
                                    </code>{' '}
                                    over{' '}
                                    <code>{securityPolicy.signed_content}</code>{' '}
                                    and reject payloads older than{' '}
                                    {securityPolicy.replay_window_seconds}{' '}
                                    seconds.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <FilterToolbar
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/company/integrations/deliveries', form.data, {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <FilterToolbarGrid className="xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <FilterField label="Search" htmlFor="search">
                            <Input
                                id="search"
                                value={form.data.search}
                                onChange={(event) =>
                                    form.setData('search', event.target.value)
                                }
                                placeholder="Endpoint or event"
                            />
                        </FilterField>

                        <FilterField label="Status" htmlFor="status">
                            <select
                                id="status"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
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
                        </FilterField>

                        <FilterField label="Event" htmlFor="event_type">
                            <select
                                id="event_type"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.event_type}
                                onChange={(event) =>
                                    form.setData(
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
                        </FilterField>

                        <FilterField label="Endpoint" htmlFor="endpoint_id">
                            <select
                                id="endpoint_id"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.endpoint_id}
                                onChange={(event) =>
                                    form.setData(
                                        'endpoint_id',
                                        event.target.value,
                                    )
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
                        </FilterField>

                        <FilterToolbarActions>
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
                                    router.get(
                                        '/company/integrations/deliveries',
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
