import ActivityTrendChart from '@/components/company/dashboard/activity-trend-chart';
import InviteStatusChart from '@/components/company/dashboard/invite-status-chart';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    ChartLine,
    MailPlus,
    PackagePlus,
    Settings2,
    ShieldCheck,
    UserPlus,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Company Dashboard',
        href: '/company/dashboard',
    },
];

type Props = {
    companySummary: {
        name: string;
        timezone?: string | null;
        currency_code?: string | null;
    };
    kpis: {
        team_members: number;
        owners: number;
        pending_invites: number;
        failed_invite_deliveries: number;
        master_data_records: number;
        activity_events_7d: number;
        activity_events_change_pct: number;
        invites_created_7d: number;
        invites_created_change_pct: number;
    };
    activityTrend: {
        date: string;
        audits: number;
        invites: number;
    }[];
    inviteStatusMix: {
        pending: number;
        accepted: number;
        expired: number;
        total: number;
    };
    masterDataBreakdown: {
        label: string;
        value: number;
    }[];
    recentActivity: {
        id: string;
        action: string;
        record_type: string;
        actor?: string | null;
        created_at?: string | null;
    }[];
};

type QuickAction = {
    title: string;
    description: string;
    href: string;
    permission?: string;
    icon: React.ComponentType<{ className?: string }>;
};

const quickActions: QuickAction[] = [
    {
        title: 'Invite teammate',
        description: 'Send a company invite for owner or member onboarding.',
        href: '/core/invites/create',
        permission: 'core.users.manage',
        icon: UserPlus,
    },
    {
        title: 'Manage users',
        description: 'Assign roles and review current company members.',
        href: '/company/users',
        permission: 'core.users.manage',
        icon: Users,
    },
    {
        title: 'Create product',
        description: 'Add a new item to the product catalog.',
        href: '/core/products/create',
        permission: 'core.products.manage',
        icon: PackagePlus,
    },
    {
        title: 'Create partner',
        description: 'Add a customer, vendor, or prospect record.',
        href: '/core/partners/create',
        permission: 'core.partners.manage',
        icon: MailPlus,
    },
    {
        title: 'Audit logs',
        description: 'Review high-signal operational events.',
        href: '/core/audit-logs',
        permission: 'core.audit_logs.view',
        icon: ShieldCheck,
    },
    {
        title: 'Company settings',
        description: 'Update profile defaults and localization settings.',
        href: '/company/settings',
        permission: 'core.company.view',
        icon: Settings2,
    },
    {
        title: 'Notifications',
        description: 'Review unread events and delivery outcomes.',
        href: '/core/notifications',
        permission: 'core.notifications.view',
        icon: Bell,
    },
];

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatPercent = (value: number) =>
    `${value > 0 ? '+' : ''}${value.toFixed(1)}%`;

const formatAction = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function CompanyDashboard({
    companySummary,
    kpis,
    activityTrend,
    inviteStatusMix,
    masterDataBreakdown,
    recentActivity,
}: Props) {
    const { hasPermission } = usePermissions();
    const availableQuickActions = quickActions.filter(
        (item) => !item.permission || hasPermission(item.permission),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Company Dashboard" />

            <div className="space-y-6">
                <section className="rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-chart-1/10 via-transparent to-chart-2/10 p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Company command center
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {companySummary.name} • Timezone{' '}
                                {companySummary.timezone ?? 'UTC'} • Currency{' '}
                                {companySummary.currency_code ?? '-'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {availableQuickActions
                                .slice(0, 2)
                                .map((action, index) => (
                                    <Button
                                        key={action.title}
                                        variant={
                                            index === 0 ? 'default' : 'outline'
                                        }
                                        asChild
                                    >
                                        <Link href={action.href}>
                                            <action.icon className="size-4" />
                                            {action.title}
                                        </Link>
                                    </Button>
                                ))}
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-xl border p-4">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Team members
                        </p>
                        <p className="mt-2 text-3xl font-semibold">
                            {kpis.team_members}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {kpis.owners} owner
                            {kpis.owners === 1 ? '' : 's'} in this workspace
                        </p>
                    </div>

                    <div className="rounded-xl border p-4">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Pending invites
                        </p>
                        <p className="mt-2 text-3xl font-semibold">
                            {kpis.pending_invites}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {kpis.failed_invite_deliveries} failed deliveries
                            awaiting retry
                        </p>
                    </div>

                    <div className="rounded-xl border p-4">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Master data records
                        </p>
                        <p className="mt-2 text-3xl font-semibold">
                            {kpis.master_data_records}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Partners, catalog, pricing, and tax baselines
                        </p>
                    </div>

                    <div className="rounded-xl border p-4">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Activity (7 days)
                        </p>
                        <p className="mt-2 text-3xl font-semibold">
                            {kpis.activity_events_7d}
                        </p>
                        <p
                            className={`mt-1 text-xs ${
                                kpis.activity_events_change_pct < 0
                                    ? 'text-destructive'
                                    : 'text-emerald-600 dark:text-emerald-400'
                            }`}
                        >
                            {formatPercent(kpis.activity_events_change_pct)} vs
                            previous 7 days
                        </p>
                    </div>
                </section>

                <section className="grid gap-4 xl:grid-cols-3">
                    <div className="rounded-2xl border p-4 xl:col-span-2">
                        <div className="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Operational activity trend
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Daily audit events and invite creation over
                                    the last 14 days.
                                </p>
                            </div>
                            <div className="rounded-md border bg-muted/30 px-2 py-1 text-xs text-muted-foreground">
                                {kpis.invites_created_7d} invites this week (
                                {formatPercent(kpis.invites_created_change_pct)}
                                )
                            </div>
                        </div>

                        <ActivityTrendChart rows={activityTrend} />
                    </div>

                    <div className="rounded-2xl border p-4">
                        <h2 className="text-base font-semibold">
                            Invite status
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Snapshot across pending, accepted, and expired
                            invites.
                        </p>

                        <div className="mt-2">
                            <InviteStatusChart
                                pending={inviteStatusMix.pending}
                                accepted={inviteStatusMix.accepted}
                                expired={inviteStatusMix.expired}
                            />
                        </div>

                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between rounded-md border px-3 py-2">
                                <span>Pending</span>
                                <span className="font-medium">
                                    {inviteStatusMix.pending}
                                </span>
                            </div>
                            <div className="flex items-center justify-between rounded-md border px-3 py-2">
                                <span>Accepted</span>
                                <span className="font-medium">
                                    {inviteStatusMix.accepted}
                                </span>
                            </div>
                            <div className="flex items-center justify-between rounded-md border px-3 py-2">
                                <span>Expired</span>
                                <span className="font-medium">
                                    {inviteStatusMix.expired}
                                </span>
                            </div>
                            <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2 text-sm font-semibold">
                                <span>Total</span>
                                <span>{inviteStatusMix.total}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 xl:grid-cols-3">
                    <div className="rounded-2xl border p-4 xl:col-span-2">
                        <h2 className="text-base font-semibold">
                            Quick actions
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            High-frequency operations for day-to-day workspace
                            management.
                        </p>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {availableQuickActions.map((action) => (
                                <Link
                                    key={action.title}
                                    href={action.href}
                                    className="group rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-muted/20 to-transparent p-3 transition-colors hover:border-primary/40 hover:bg-muted/40"
                                >
                                    <div className="flex items-start gap-3">
                                        <span className="rounded-md border bg-background/80 p-2">
                                            <action.icon className="size-4 text-muted-foreground transition-colors group-hover:text-foreground" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {action.title}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {action.description}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border p-4">
                        <h2 className="text-base font-semibold">
                            Recent activity
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Latest audit events in this company.
                        </p>

                        <div className="mt-4 space-y-2">
                            {recentActivity.length === 0 && (
                                <div className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                    No recent activity yet.
                                </div>
                            )}
                            {recentActivity.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-md border px-3 py-2"
                                >
                                    <p className="text-sm font-medium">
                                        {formatAction(item.action)}{' '}
                                        <span className="text-muted-foreground">
                                            {item.record_type}
                                        </span>
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {(item.actor ?? 'System') +
                                            ' • ' +
                                            formatDate(item.created_at)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border p-4">
                    <div className="mb-3 flex items-center gap-2">
                        <ChartLine className="size-4 text-muted-foreground" />
                        <h2 className="text-base font-semibold">
                            Master data breakdown
                        </h2>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {masterDataBreakdown.map((item) => (
                            <div
                                key={item.label}
                                className="rounded-md border bg-muted/20 px-3 py-2"
                            >
                                <p className="text-xs text-muted-foreground">
                                    {item.label}
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {item.value}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
