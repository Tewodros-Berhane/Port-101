import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

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
    notificationGovernance: {
        min_severity: 'low' | 'medium' | 'high' | 'critical';
        escalation_enabled: boolean;
        escalation_severity: 'low' | 'medium' | 'high' | 'critical';
        escalation_delay_minutes: number;
        digest_enabled: boolean;
        digest_frequency: 'daily' | 'weekly';
        digest_day_of_week: number;
        digest_time: string;
        digest_timezone: string;
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

const formatRole = (role: string) =>
    role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatAction = (action: string) =>
    action.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function PlatformDashboard({
    stats,
    recentCompanies,
    recentInvites,
    recentAdminActions,
    deliverySummary,
    deliveryTrend,
    operationsFilters,
    adminFilterOptions,
    notificationGovernance,
}: Props) {
    const form = useForm({
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
    });
    const governanceForm = useForm({
        min_severity: notificationGovernance.min_severity ?? 'low',
        escalation_enabled: notificationGovernance.escalation_enabled ? '1' : '0',
        escalation_severity: notificationGovernance.escalation_severity ?? 'high',
        escalation_delay_minutes:
            notificationGovernance.escalation_delay_minutes ?? 30,
        digest_enabled: notificationGovernance.digest_enabled ? '1' : '0',
        digest_frequency: notificationGovernance.digest_frequency ?? 'daily',
        digest_day_of_week: notificationGovernance.digest_day_of_week ?? 1,
        digest_time: notificationGovernance.digest_time ?? '08:00',
        digest_timezone: notificationGovernance.digest_timezone ?? 'UTC',
    });

    const trendRows = [...deliveryTrend].reverse().slice(0, 14);
    const exportParams = new URLSearchParams({
        trend_window: form.data.trend_window,
        admin_action: form.data.admin_action,
        admin_actor_id: form.data.admin_actor_id,
        admin_start_date: form.data.admin_start_date,
        admin_end_date: form.data.admin_end_date,
    });
    const exportQuery = exportParams.toString();
    const exportAdminActionsCsvUrl =
        `/platform/dashboard/export/admin-actions?${exportQuery}&format=csv`;
    const exportAdminActionsJsonUrl =
        `/platform/dashboard/export/admin-actions?${exportQuery}&format=json`;
    const exportDeliveryTrendsCsvUrl =
        `/platform/dashboard/export/delivery-trends?${exportQuery}&format=csv`;
    const exportDeliveryTrendsJsonUrl =
        `/platform/dashboard/export/delivery-trends?${exportQuery}&format=json`;

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Platform', href: '/platform/dashboard' }]}
        >
            <Head title="Platform Dashboard" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">
                        Platform dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Overview of platform activity and recent companies.
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
                            Filter admin actions and adjust invite delivery
                            trend windows.
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
                    <Button variant="outline" asChild>
                        <a href={exportAdminActionsCsvUrl}>
                            Export admin actions CSV
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={exportAdminActionsJsonUrl}>
                            Export admin actions JSON
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={exportDeliveryTrendsCsvUrl}>
                            Export delivery trends CSV
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={exportDeliveryTrendsJsonUrl}>
                            Export delivery trends JSON
                        </a>
                    </Button>
                </div>
            </form>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    governanceForm.put(
                        '/platform/dashboard/notification-governance',
                        {
                            preserveScroll: true,
                        },
                    );
                }}
            >
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Notification governance controls
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Configure minimum severity, escalation rules, and
                            digest policy.
                        </p>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="min_severity">Minimum severity</Label>
                        <select
                            id="min_severity"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={governanceForm.data.min_severity}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'min_severity',
                                    event.target.value as
                                        | 'low'
                                        | 'medium'
                                        | 'high'
                                        | 'critical',
                                )
                            }
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="escalation_enabled">
                            Escalation
                        </Label>
                        <select
                            id="escalation_enabled"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={governanceForm.data.escalation_enabled}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'escalation_enabled',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="0">Disabled</option>
                            <option value="1">Enabled</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="escalation_severity">
                            Escalation severity
                        </Label>
                        <select
                            id="escalation_severity"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={governanceForm.data.escalation_severity}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'escalation_severity',
                                    event.target.value as
                                        | 'low'
                                        | 'medium'
                                        | 'high'
                                        | 'critical',
                                )
                            }
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="escalation_delay_minutes">
                            Escalation delay (minutes)
                        </Label>
                        <Input
                            id="escalation_delay_minutes"
                            type="number"
                            min={1}
                            max={1440}
                            value={String(
                                governanceForm.data.escalation_delay_minutes,
                            )}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'escalation_delay_minutes',
                                    Number(event.target.value || 1),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="digest_enabled">Digest policy</Label>
                        <select
                            id="digest_enabled"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={governanceForm.data.digest_enabled}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'digest_enabled',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="0">Disabled</option>
                            <option value="1">Enabled</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="digest_frequency">
                            Digest frequency
                        </Label>
                        <select
                            id="digest_frequency"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={governanceForm.data.digest_frequency}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'digest_frequency',
                                    event.target.value as 'daily' | 'weekly',
                                )
                            }
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="digest_day_of_week">
                            Digest weekday (weekly)
                        </Label>
                        <select
                            id="digest_day_of_week"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={String(governanceForm.data.digest_day_of_week)}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'digest_day_of_week',
                                    Number(event.target.value),
                                )
                            }
                        >
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="digest_time">Digest time</Label>
                        <Input
                            id="digest_time"
                            type="time"
                            value={governanceForm.data.digest_time}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'digest_time',
                                    event.target.value,
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="digest_timezone">Digest timezone</Label>
                        <Input
                            id="digest_timezone"
                            value={governanceForm.data.digest_timezone}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'digest_timezone',
                                    event.target.value,
                                )
                            }
                            placeholder="UTC"
                        />
                    </div>
                </div>

                <div className="mt-4 flex items-center gap-3">
                    <Button type="submit" disabled={governanceForm.processing}>
                        Save governance controls
                    </Button>
                </div>
            </form>

            <div className="mt-6 grid gap-4 md:grid-cols-4">
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Companies</p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.companies}
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Active</p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.active_companies}
                    </p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Users</p>
                    <p className="mt-2 text-2xl font-semibold">{stats.users}</p>
                </div>
                <div className="rounded-xl border p-4">
                    <p className="text-sm text-muted-foreground">Audit logs</p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.audit_logs}
                    </p>
                </div>
            </div>

            <div className="mt-8">
                <h2 className="text-lg font-semibold">Delivery trends</h2>
                <p className="text-sm text-muted-foreground">
                    Invite delivery performance over the last{' '}
                    {deliverySummary.window_days} days.
                </p>

                <div className="mt-4 grid gap-4 md:grid-cols-5">
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">Total</p>
                        <p className="mt-2 text-2xl font-semibold">
                            {deliverySummary.total}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">Sent</p>
                        <p className="mt-2 text-2xl font-semibold">
                            {deliverySummary.sent}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">Failed</p>
                        <p className="mt-2 text-2xl font-semibold">
                            {deliverySummary.failed}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">Pending</p>
                        <p className="mt-2 text-2xl font-semibold">
                            {deliverySummary.pending}
                        </p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-sm text-muted-foreground">
                            Failure rate
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {deliverySummary.failure_rate}%
                        </p>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-max text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 font-medium">Sent</th>
                                <th className="px-4 py-3 font-medium">
                                    Failed
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Pending
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {trendRows.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No delivery trend data available.
                                    </td>
                                </tr>
                            )}
                            {trendRows.map((row) => (
                                <tr key={row.date}>
                                    <td className="px-4 py-3">{row.date}</td>
                                    <td className="px-4 py-3">{row.sent}</td>
                                    <td className="px-4 py-3">{row.failed}</td>
                                    <td className="px-4 py-3">{row.pending}</td>
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
                                        {company.owner ?? '—'}
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
                                                {invite.company ?? '—'}
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
