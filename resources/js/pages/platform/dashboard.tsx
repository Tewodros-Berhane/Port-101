import DeliveryStatusDonut from '@/components/platform/dashboard/delivery-status-donut';
import DeliveryTrendChart from '@/components/platform/dashboard/delivery-trend-chart';
import NoisyEventsChart from '@/components/platform/dashboard/noisy-events-chart';
import OperationsExportMenu from '@/components/platform/dashboard/operations-export-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';

type Props = {
    stats: {
        companies: number;
        active_companies: number;
        users: number;
        audit_logs: number;
    };
    recentCompanies: {
        id: string;
        name: string;
        slug: string;
        owner?: string | null;
        is_active: boolean;
        created_at?: string | null;
    }[];
    recentInvites: {
        id: string;
        email: string;
        role: string;
        company?: string | null;
        status: string;
        delivery_status?: string | null;
        created_by?: string | null;
        created_at?: string | null;
    }[];
    recentAdminActions: {
        id: string;
        action: string;
        record_type: string;
        record_id: string;
        company?: string | null;
        actor?: string | null;
        created_at?: string | null;
    }[];
    deliverySummary: {
        window_days: number;
        sent: number;
        failed: number;
        pending: number;
        total: number;
        failure_rate: number;
    };
    deliveryTrend: {
        date: string;
        sent: number;
        failed: number;
        pending: number;
    }[];
    operationsFilters: {
        trend_window: number;
        admin_action?: string | null;
        admin_actor_id?: string | null;
        admin_start_date?: string | null;
        admin_end_date?: string | null;
    };
    adminFilterOptions: {
        actions: string[];
        actors: {
            id: string;
            name: string;
        }[];
    };
    notificationGovernanceAnalytics: {
        window_days: number;
        escalations: {
            triggered: number;
            acknowledged: number;
            pending: number;
            acknowledgement_rate: number;
        };
        digest_coverage: {
            sent: number;
            opened: number;
            open_rate: number;
            total_notifications_summarized: number;
        };
        noisy_events: {
            event: string;
            count: number;
            unread: number;
            high_or_critical: number;
        }[];
    };
    operationsReportPresets: {
        id: string;
        name: string;
        filters: {
            trend_window: number;
            admin_action?: string | null;
            admin_actor_id?: string | null;
            admin_start_date?: string | null;
            admin_end_date?: string | null;
        };
        created_at?: string | null;
    }[];
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatRole = (role: string) =>
    role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatAction = (action: string) =>
    action.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatPercent = (value: number) => `${value}%`;

export default function PlatformDashboard({
    stats,
    recentCompanies,
    recentInvites,
    recentAdminActions,
    deliverySummary,
    deliveryTrend,
    operationsFilters,
    adminFilterOptions,
    notificationGovernanceAnalytics,
    operationsReportPresets,
}: Props) {
    const form = useForm({
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
    });
    const deletePresetForm = useForm({});
    const presetForm = useForm({
        name: '',
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
    });

    const trendRows = [...deliveryTrend].reverse();
    const exportParams = new URLSearchParams({
        trend_window: form.data.trend_window,
        admin_action: form.data.admin_action,
        admin_actor_id: form.data.admin_actor_id,
        admin_start_date: form.data.admin_start_date,
        admin_end_date: form.data.admin_end_date,
    });
    const exportQuery = exportParams.toString();
    const exportAdminActionsCsvUrl = `/platform/dashboard/export/admin-actions?${exportQuery}&format=csv`;
    const exportAdminActionsJsonUrl = `/platform/dashboard/export/admin-actions?${exportQuery}&format=json`;
    const exportDeliveryTrendsCsvUrl = `/platform/dashboard/export/delivery-trends?${exportQuery}&format=csv`;
    const exportDeliveryTrendsJsonUrl = `/platform/dashboard/export/delivery-trends?${exportQuery}&format=json`;

    const presetQuery = (preset: Props['operationsReportPresets'][number]) => {
        const params = new URLSearchParams({
            trend_window: String(preset.filters.trend_window ?? 30),
            admin_action: preset.filters.admin_action ?? '',
            admin_actor_id: preset.filters.admin_actor_id ?? '',
            admin_start_date: preset.filters.admin_start_date ?? '',
            admin_end_date: preset.filters.admin_end_date ?? '',
        });

        return params.toString();
    };

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Platform', href: '/platform/dashboard' }]}
        >
            <Head title="Platform Dashboard" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Platform dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor platform health, delivery quality, and admin
                        activity.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href="/platform/governance">
                        <Settings2 className="size-4" />
                        Governance settings
                    </Link>
                </Button>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Companies
                    </p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.companies}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Active: {stats.active_companies}
                    </p>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Users
                    </p>
                    <p className="mt-2 text-2xl font-semibold">{stats.users}</p>
                    <p className="text-xs text-muted-foreground">
                        Platform and company members
                    </p>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Audit logs
                    </p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.audit_logs}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Total tracked platform events
                    </p>
                </div>
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Invite failure rate
                    </p>
                    <p className="mt-2 text-2xl font-semibold">
                        {formatPercent(deliverySummary.failure_rate)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Last {deliverySummary.window_days} days
                    </p>
                </div>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/platform/dashboard', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Operations reporting filters
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Filter admin actions and refresh delivery reporting
                            in one place.
                        </p>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-5">
                    <div className="grid gap-2">
                        <Label htmlFor="trend_window">Trend window</Label>
                        <select
                            id="trend_window"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.trend_window}
                            onChange={(event) =>
                                form.setData('trend_window', event.target.value)
                            }
                        >
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin_action">Admin action</Label>
                        <select
                            id="admin_action"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.admin_action}
                            onChange={(event) =>
                                form.setData('admin_action', event.target.value)
                            }
                        >
                            <option value="">All actions</option>
                            {adminFilterOptions.actions.map((action) => (
                                <option key={action} value={action}>
                                    {formatAction(action)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin_actor_id">Admin actor</Label>
                        <select
                            id="admin_actor_id"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.admin_actor_id}
                            onChange={(event) =>
                                form.setData(
                                    'admin_actor_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">All platform admins</option>
                            {adminFilterOptions.actors.map((actor) => (
                                <option key={actor.id} value={actor.id}>
                                    {actor.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin_start_date">Start date</Label>
                        <Input
                            id="admin_start_date"
                            type="date"
                            value={form.data.admin_start_date}
                            onChange={(event) =>
                                form.setData(
                                    'admin_start_date',
                                    event.target.value,
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin_end_date">End date</Label>
                        <Input
                            id="admin_end_date"
                            type="date"
                            value={form.data.admin_end_date}
                            onChange={(event) =>
                                form.setData(
                                    'admin_end_date',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Apply filters
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/platform/dashboard">Reset</Link>
                    </Button>
                    <div className="flex min-w-[260px] flex-1 items-center gap-2">
                        <Input
                            value={presetForm.data.name}
                            onChange={(event) =>
                                presetForm.setData('name', event.target.value)
                            }
                            placeholder="Preset name"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                presetForm.processing ||
                                presetForm.data.name.trim() === ''
                            }
                            onClick={() => {
                                presetForm.transform((data) => ({
                                    ...data,
                                    trend_window: form.data.trend_window,
                                    admin_action: form.data.admin_action,
                                    admin_actor_id: form.data.admin_actor_id,
                                    admin_start_date:
                                        form.data.admin_start_date,
                                    admin_end_date: form.data.admin_end_date,
                                }));

                                presetForm.post(
                                    '/platform/dashboard/report-presets',
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            presetForm.reset('name'),
                                    },
                                );
                            }}
                        >
                            Save preset
                        </Button>
                    </div>
                    <OperationsExportMenu
                        adminActionsCsvUrl={exportAdminActionsCsvUrl}
                        adminActionsJsonUrl={exportAdminActionsJsonUrl}
                        deliveryTrendsCsvUrl={exportDeliveryTrendsCsvUrl}
                        deliveryTrendsJsonUrl={exportDeliveryTrendsJsonUrl}
                    />
                </div>
            </form>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Delivery performance
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Invite delivery outcomes for the last{' '}
                            {deliverySummary.window_days} days.
                        </p>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 xl:grid-cols-3">
                    <div className="rounded-xl border p-4 xl:col-span-2">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Delivery trend
                        </p>
                        <div className="mt-3">
                            <DeliveryTrendChart rows={trendRows} />
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Status mix
                        </p>
                        <div className="mt-3">
                            <DeliveryStatusDonut
                                sent={deliverySummary.sent}
                                failed={deliverySummary.failed}
                                pending={deliverySummary.pending}
                            />
                        </div>
                        <div className="mt-4 space-y-2 text-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Sent
                                </span>
                                <span className="font-medium">
                                    {deliverySummary.sent}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Failed
                                </span>
                                <span className="font-medium">
                                    {deliverySummary.failed}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Pending
                                </span>
                                <span className="font-medium">
                                    {deliverySummary.pending}
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Total
                                </span>
                                <span className="font-medium">
                                    {deliverySummary.total}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Governance analytics snapshot
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Escalation outcomes and digest coverage for the
                            current reporting window (
                            {notificationGovernanceAnalytics.window_days} days).
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/platform/governance">
                            Open governance controls
                        </Link>
                    </Button>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Escalations
                        </p>
                        <p className="mt-1 text-xl font-semibold">
                            {
                                notificationGovernanceAnalytics.escalations
                                    .triggered
                            }
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Ack:{' '}
                            {
                                notificationGovernanceAnalytics.escalations
                                    .acknowledged
                            }{' '}
                            | Pending:{' '}
                            {
                                notificationGovernanceAnalytics.escalations
                                    .pending
                            }{' '}
                            | Ack rate:{' '}
                            {formatPercent(
                                notificationGovernanceAnalytics.escalations
                                    .acknowledgement_rate,
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Digest coverage
                        </p>
                        <p className="mt-1 text-xl font-semibold">
                            {
                                notificationGovernanceAnalytics.digest_coverage
                                    .sent
                            }
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Opened:{' '}
                            {
                                notificationGovernanceAnalytics.digest_coverage
                                    .opened
                            }{' '}
                            | Open rate:{' '}
                            {formatPercent(
                                notificationGovernanceAnalytics.digest_coverage
                                    .open_rate,
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Notifications summarized
                        </p>
                        <p className="mt-1 text-xl font-semibold">
                            {
                                notificationGovernanceAnalytics.digest_coverage
                                    .total_notifications_summarized
                            }
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Included in digest payloads.
                        </p>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 xl:grid-cols-3">
                    <div className="rounded-xl border p-4 xl:col-span-2">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Top noisy events
                        </p>
                        <div className="mt-3">
                            <NoisyEventsChart
                                rows={
                                    notificationGovernanceAnalytics.noisy_events
                                }
                            />
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Event details
                        </p>
                        <div className="mt-3 space-y-3">
                            {notificationGovernanceAnalytics.noisy_events
                                .slice(0, 5)
                                .map((event) => (
                                    <div
                                        className="rounded-lg border p-3"
                                        key={event.event}
                                    >
                                        <p className="line-clamp-2 text-sm font-medium">
                                            {event.event}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Count: {event.count} | Unread:{' '}
                                            {event.unread} | High/Critical:{' '}
                                            {event.high_or_critical}
                                        </p>
                                    </div>
                                ))}
                            {notificationGovernanceAnalytics.noisy_events
                                .length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No noisy events detected.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Saved operations presets
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Reapply common filter combinations.
                        </p>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto rounded-md border">
                    <table className="w-full min-w-max text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Preset
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Filters
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Created
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {operationsReportPresets.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No saved presets yet.
                                    </td>
                                </tr>
                            )}
                            {operationsReportPresets.map((preset) => (
                                <tr key={preset.id}>
                                    <td className="px-3 py-2 font-medium">
                                        {preset.name}
                                    </td>
                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                        Window: {preset.filters.trend_window}d
                                        {' | '}Action:{' '}
                                        {preset.filters.admin_action
                                            ? formatAction(
                                                  preset.filters.admin_action,
                                              )
                                            : 'All'}
                                        {' | '}Actor:{' '}
                                        {preset.filters.admin_actor_id
                                            ? 'Specific'
                                            : 'All'}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {formatDate(preset.created_at)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <div className="inline-flex items-center gap-2">
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={`/platform/dashboard?${presetQuery(preset)}`}
                                                >
                                                    Apply
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                type="button"
                                                onClick={() =>
                                                    deletePresetForm.delete(
                                                        `/platform/dashboard/report-presets/${preset.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                disabled={
                                                    deletePresetForm.processing
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-8">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Recent companies</h2>
                    <Link
                        href="/platform/companies"
                        className="text-sm font-medium text-primary"
                    >
                        View all
                    </Link>
                </div>

                <div className="mt-4 overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-max text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Owner</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Created
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {recentCompanies.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No companies yet.
                                    </td>
                                </tr>
                            )}
                            {recentCompanies.map((company) => (
                                <tr key={company.id}>
                                    <td className="px-4 py-3 font-medium">
                                        {company.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.owner ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatDate(company.created_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-2">
                <div>
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            Recent invites
                        </h2>
                        <Link
                            href="/platform/invites"
                            className="text-sm font-medium text-primary"
                        >
                            View all
                        </Link>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-max text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Delivery
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentInvites.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={4}
                                        >
                                            No invites yet.
                                        </td>
                                    </tr>
                                )}
                                {recentInvites.map((invite) => (
                                    <tr key={invite.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {invite.email}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {invite.company ?? '-'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatRole(invite.role)}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {invite.status}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {invite.delivery_status ??
                                                'pending'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h2 className="text-lg font-semibold">Admin actions</h2>
                    <p className="text-sm text-muted-foreground">
                        Filtered platform admin audit events.
                    </p>

                    <div className="mt-4 overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-max text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Action
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Record
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Actor
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentAdminActions.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={4}
                                        >
                                            No admin actions yet.
                                        </td>
                                    </tr>
                                )}
                                {recentAdminActions.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 capitalize">
                                            {item.action}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {item.record_type}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {item.company ?? 'Platform'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.actor ?? 'System'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDate(item.created_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
