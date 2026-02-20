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
import { useEffect, useState } from 'react';

type OperationsTab = 'companies' | 'invites' | 'admin_actions';
type WidgetId =
    | 'delivery_performance'
    | 'governance_snapshot'
    | 'operations_presets'
    | 'operations_detail';

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
        invite_delivery_status?: 'pending' | 'sent' | 'failed' | null;
    };
    operationsTab: OperationsTab;
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
            invite_delivery_status?: 'pending' | 'sent' | 'failed' | null;
        };
        created_at?: string | null;
    }[];
    dashboardPreferences: {
        default_preset_id?: string | null;
        default_operations_tab?: OperationsTab;
        layout?: 'balanced' | 'analytics_first' | 'operations_first';
        hidden_widgets?: WidgetId[];
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatRole = (role: string) =>
    role.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatAction = (action: string) =>
    action.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatPercent = (value: number) => `${value}%`;

const widgetLabels: Record<WidgetId, string> = {
    delivery_performance: 'Delivery performance',
    governance_snapshot: 'Governance snapshot',
    operations_presets: 'Saved presets',
    operations_detail: 'Operations detail tabs',
};

const sectionOrderByLayout: Record<
    NonNullable<Props['dashboardPreferences']['layout']>,
    WidgetId[]
> = {
    balanced: [
        'delivery_performance',
        'governance_snapshot',
        'operations_presets',
        'operations_detail',
    ],
    analytics_first: [
        'delivery_performance',
        'governance_snapshot',
        'operations_detail',
        'operations_presets',
    ],
    operations_first: [
        'operations_detail',
        'operations_presets',
        'delivery_performance',
        'governance_snapshot',
    ],
};

const makeTabTitle = (tab: OperationsTab) => {
    if (tab === 'admin_actions') {
        return 'Admin actions';
    }

    return tab === 'invites' ? 'Invites' : 'Companies';
};

export default function PlatformDashboard({
    stats,
    recentCompanies,
    recentInvites,
    recentAdminActions,
    deliverySummary,
    deliveryTrend,
    operationsFilters,
    operationsTab,
    adminFilterOptions,
    notificationGovernanceAnalytics,
    operationsReportPresets,
    dashboardPreferences,
}: Props) {
    const form = useForm({
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
        invite_delivery_status: operationsFilters.invite_delivery_status ?? '',
        operations_tab: operationsTab,
    });
    const deletePresetForm = useForm({});
    const presetForm = useForm({
        name: '',
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
        invite_delivery_status: operationsFilters.invite_delivery_status ?? '',
    });
    const preferencesForm = useForm({
        default_preset_id: dashboardPreferences.default_preset_id ?? '',
        default_operations_tab:
            dashboardPreferences.default_operations_tab ?? 'companies',
        layout: dashboardPreferences.layout ?? 'balanced',
        hidden_widgets: dashboardPreferences.hidden_widgets ?? [],
    });
    const [selectedOperationsTab, setSelectedOperationsTab] =
        useState<OperationsTab>(operationsTab);

    useEffect(() => {
        setSelectedOperationsTab(operationsTab);
    }, [operationsTab]);

    useEffect(() => {
        form.setData('operations_tab', selectedOperationsTab);
        // Reflect tab state in URL without issuing a network request.
        const url = new URL(window.location.href);
        if (url.searchParams.get('operations_tab') !== selectedOperationsTab) {
            url.searchParams.set('operations_tab', selectedOperationsTab);
            window.history.replaceState(
                window.history.state,
                '',
                `${url.pathname}?${url.searchParams.toString()}`,
            );
        }
    }, [form, selectedOperationsTab]);

    const trendRows = [...deliveryTrend].reverse();
    const activeLayout = preferencesForm.data.layout;
    const hiddenWidgets = new Set(preferencesForm.data.hidden_widgets);
    const buildQuery = (overrides: Partial<typeof form.data> = {}) => {
        const merged = {
            trend_window: form.data.trend_window,
            admin_action: form.data.admin_action,
            admin_actor_id: form.data.admin_actor_id,
            admin_start_date: form.data.admin_start_date,
            admin_end_date: form.data.admin_end_date,
            invite_delivery_status: form.data.invite_delivery_status,
            operations_tab: selectedOperationsTab,
            ...overrides,
        };

        return new URLSearchParams(
            Object.entries(merged).reduce(
                (carry, [key, value]) => ({
                    ...carry,
                    [key]: String(value ?? ''),
                }),
                {} as Record<string, string>,
            ),
        ).toString();
    };

    const exportQuery = buildQuery();
    const exportAdminActionsCsvUrl = `/platform/dashboard/export/admin-actions?${exportQuery}&format=csv`;
    const exportAdminActionsJsonUrl = `/platform/dashboard/export/admin-actions?${exportQuery}&format=json`;
    const exportDeliveryTrendsCsvUrl = `/platform/dashboard/export/delivery-trends?${exportQuery}&format=csv`;
    const exportDeliveryTrendsJsonUrl = `/platform/dashboard/export/delivery-trends?${exportQuery}&format=json`;
    const tabHref = (tab: OperationsTab) =>
        `/platform/dashboard?${buildQuery({ operations_tab: tab })}`;

    const toggleWidgetVisibility = (widget: WidgetId, visible: boolean) => {
        const current = [...preferencesForm.data.hidden_widgets];

        if (visible) {
            preferencesForm.setData(
                'hidden_widgets',
                current.filter((item) => item !== widget),
            );

            return;
        }

        if (!current.includes(widget)) {
            preferencesForm.setData('hidden_widgets', [...current, widget]);
        }
    };

    const sectionOrder = sectionOrderByLayout[activeLayout].filter(
        (widget) => !hiddenWidgets.has(widget),
    );

    const presetQuery = (preset: Props['operationsReportPresets'][number]) => {
        const params = new URLSearchParams({
            trend_window: String(preset.filters.trend_window ?? 30),
            admin_action: preset.filters.admin_action ?? '',
            admin_actor_id: preset.filters.admin_actor_id ?? '',
            admin_start_date: preset.filters.admin_start_date ?? '',
            admin_end_date: preset.filters.admin_end_date ?? '',
            invite_delivery_status: preset.filters.invite_delivery_status ?? '',
            operations_tab: selectedOperationsTab,
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
                <Link
                    href={tabHref('companies')}
                    preserveState
                    preserveScroll
                    className="rounded-xl border bg-card p-4 transition-colors hover:border-primary/40"
                >
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Companies
                    </p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.companies}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Active: {stats.active_companies}
                    </p>
                </Link>
                <div className="rounded-xl border bg-card p-4">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Users
                    </p>
                    <p className="mt-2 text-2xl font-semibold">{stats.users}</p>
                    <p className="text-xs text-muted-foreground">
                        Platform and company members
                    </p>
                </div>
                <Link
                    href={tabHref('admin_actions')}
                    preserveState
                    preserveScroll
                    className="rounded-xl border bg-card p-4 transition-colors hover:border-primary/40"
                >
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Audit logs
                    </p>
                    <p className="mt-2 text-2xl font-semibold">
                        {stats.audit_logs}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Drill down to admin actions
                    </p>
                </Link>
                <Link
                    href={`/platform/dashboard?${buildQuery({
                        operations_tab: 'invites',
                        invite_delivery_status: 'failed',
                    })}`}
                    preserveState
                    preserveScroll
                    className="rounded-xl border bg-card p-4 transition-colors hover:border-primary/40"
                >
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Invite failure rate
                    </p>
                    <p className="mt-2 text-2xl font-semibold">
                        {formatPercent(deliverySummary.failure_rate)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Open failed invites drill-down
                    </p>
                </Link>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        operations_tab: selectedOperationsTab,
                    }));
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
                            Shared filter state for charts, exports, and
                            operations tabs.
                        </p>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
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
                        <Label htmlFor="invite_delivery_status">
                            Invite delivery
                        </Label>
                        <select
                            id="invite_delivery_status"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.invite_delivery_status}
                            onChange={(event) =>
                                form.setData(
                                    'invite_delivery_status',
                                    event.target.value as
                                        | ''
                                        | 'pending'
                                        | 'sent'
                                        | 'failed',
                                )
                            }
                        >
                            <option value="">All delivery states</option>
                            <option value="pending">Pending</option>
                            <option value="sent">Sent</option>
                            <option value="failed">Failed</option>
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
                        <Link
                            href={`/platform/dashboard?${new URLSearchParams({
                                operations_tab: selectedOperationsTab,
                            }).toString()}`}
                        >
                            Reset
                        </Link>
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
                                    invite_delivery_status:
                                        form.data.invite_delivery_status,
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

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    preferencesForm.put('/platform/dashboard/preferences', {
                        preserveScroll: true,
                    });
                }}
            >
                <div>
                    <h2 className="text-sm font-semibold">
                        Dashboard personalization
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Save widget layout, default filter preset, and default
                        operations tab per superadmin account.
                    </p>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="default_preset_id">
                            Default preset
                        </Label>
                        <select
                            id="default_preset_id"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={preferencesForm.data.default_preset_id}
                            onChange={(event) =>
                                preferencesForm.setData(
                                    'default_preset_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">No default preset</option>
                            {operationsReportPresets.map((preset) => (
                                <option key={preset.id} value={preset.id}>
                                    {preset.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="default_operations_tab">
                            Default operations tab
                        </Label>
                        <select
                            id="default_operations_tab"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={preferencesForm.data.default_operations_tab}
                            onChange={(event) =>
                                preferencesForm.setData(
                                    'default_operations_tab',
                                    event.target.value as OperationsTab,
                                )
                            }
                        >
                            <option value="companies">Companies</option>
                            <option value="invites">Invites</option>
                            <option value="admin_actions">Admin actions</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="layout">Widget layout</Label>
                        <select
                            id="layout"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={preferencesForm.data.layout}
                            onChange={(event) =>
                                preferencesForm.setData(
                                    'layout',
                                    event.target.value as
                                        | 'balanced'
                                        | 'analytics_first'
                                        | 'operations_first',
                                )
                            }
                        >
                            <option value="balanced">Balanced</option>
                            <option value="analytics_first">
                                Analytics first
                            </option>
                            <option value="operations_first">
                                Operations first
                            </option>
                        </select>
                    </div>
                </div>

                <div className="mt-4 rounded-lg border p-3">
                    <p className="text-xs font-medium text-muted-foreground">
                        Visible widgets
                    </p>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {(Object.keys(widgetLabels) as WidgetId[]).map(
                            (widget) => {
                                const visible =
                                    !preferencesForm.data.hidden_widgets.includes(
                                        widget,
                                    );

                                return (
                                    <label
                                        key={widget}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={visible}
                                            onChange={(event) =>
                                                toggleWidgetVisibility(
                                                    widget,
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        <span>{widgetLabels[widget]}</span>
                                    </label>
                                );
                            },
                        )}
                    </div>
                </div>

                <div className="mt-4">
                    <Button type="submit" disabled={preferencesForm.processing}>
                        Save personalization
                    </Button>
                </div>
            </form>

            <div className="mt-6 flex flex-col gap-6">
                {!hiddenWidgets.has('delivery_performance') && (
                    <div
                        className="rounded-xl border p-4"
                        style={{
                            order:
                                sectionOrder.indexOf('delivery_performance') +
                                1,
                        }}
                    >
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
                                <div className="mt-3 space-y-2 border-t pt-3 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span className="inline-flex items-center gap-2 text-muted-foreground">
                                            <span className="size-2 rounded-full bg-primary" />
                                            Sent
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {deliverySummary.sent}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="inline-flex items-center gap-2 text-muted-foreground">
                                            <span className="size-2 rounded-full bg-destructive" />
                                            Failed
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {deliverySummary.failed}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="inline-flex items-center gap-2 text-muted-foreground">
                                            <span className="size-2 rounded-full bg-muted-foreground" />
                                            Pending
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {deliverySummary.pending}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between border-t pt-2">
                                        <span className="text-muted-foreground">
                                            Total
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {deliverySummary.total}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {!hiddenWidgets.has('governance_snapshot') && (
                    <div
                        className="rounded-xl border p-4"
                        style={{
                            order:
                                sectionOrder.indexOf('governance_snapshot') + 1,
                        }}
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Governance analytics snapshot
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Escalation outcomes and digest coverage for
                                    the current reporting window (
                                    {
                                        notificationGovernanceAnalytics.window_days
                                    }{' '}
                                    days).
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
                                        notificationGovernanceAnalytics
                                            .escalations.triggered
                                    }
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Ack:{' '}
                                    {
                                        notificationGovernanceAnalytics
                                            .escalations.acknowledged
                                    }{' '}
                                    | Pending:{' '}
                                    {
                                        notificationGovernanceAnalytics
                                            .escalations.pending
                                    }{' '}
                                    | Ack rate:{' '}
                                    {formatPercent(
                                        notificationGovernanceAnalytics
                                            .escalations.acknowledgement_rate,
                                    )}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Digest coverage
                                </p>
                                <p className="mt-1 text-xl font-semibold">
                                    {
                                        notificationGovernanceAnalytics
                                            .digest_coverage.sent
                                    }
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Opened:{' '}
                                    {
                                        notificationGovernanceAnalytics
                                            .digest_coverage.opened
                                    }{' '}
                                    | Open rate:{' '}
                                    {formatPercent(
                                        notificationGovernanceAnalytics
                                            .digest_coverage.open_rate,
                                    )}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Notifications summarized
                                </p>
                                <p className="mt-1 text-xl font-semibold">
                                    {
                                        notificationGovernanceAnalytics
                                            .digest_coverage
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
                                                    Count: {event.count} |
                                                    Unread: {event.unread} |
                                                    High/Critical:{' '}
                                                    {event.high_or_critical}
                                                </p>
                                            </div>
                                        ))}
                                    {notificationGovernanceAnalytics
                                        .noisy_events.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No noisy events detected.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {!hiddenWidgets.has('operations_presets') && (
                    <div
                        className="rounded-xl border p-4"
                        style={{
                            order:
                                sectionOrder.indexOf('operations_presets') + 1,
                        }}
                    >
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
                                                Window:{' '}
                                                {preset.filters.trend_window}d
                                                {' | '}Action:{' '}
                                                {preset.filters.admin_action
                                                    ? formatAction(
                                                          preset.filters
                                                              .admin_action,
                                                      )
                                                    : 'All'}
                                                {' | '}Actor:{' '}
                                                {preset.filters.admin_actor_id
                                                    ? 'Specific'
                                                    : 'All'}
                                                {' | '}Invite delivery:{' '}
                                                {preset.filters
                                                    .invite_delivery_status
                                                    ? formatRole(
                                                          preset.filters
                                                              .invite_delivery_status,
                                                      )
                                                    : 'All'}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {formatDate(preset.created_at)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="inline-flex items-center gap-2">
                                                    <Button
                                                        variant="outline"
                                                        asChild
                                                    >
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
                )}

                {!hiddenWidgets.has('operations_detail') && (
                    <div
                        className="rounded-xl border p-4"
                        style={{
                            order:
                                sectionOrder.indexOf('operations_detail') + 1,
                        }}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Operations detail
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Drill down into platform tables with shared
                                    filters.
                                </p>
                            </div>
                            <div className="inline-flex rounded-lg border p-1">
                                {(
                                    [
                                        'companies',
                                        'invites',
                                        'admin_actions',
                                    ] as OperationsTab[]
                                ).map((tab) => {
                                    const active =
                                        selectedOperationsTab === tab;

                                    return (
                                        <Button
                                            key={tab}
                                            variant={
                                                active ? 'secondary' : 'ghost'
                                            }
                                            size="sm"
                                            type="button"
                                            onClick={() =>
                                                setSelectedOperationsTab(tab)
                                            }
                                        >
                                            {makeTabTitle(tab)}
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>

                        {selectedOperationsTab === 'companies' && (
                            <div className="mt-4 overflow-x-auto rounded-xl border">
                                <table className="w-full min-w-max text-sm">
                                    <thead className="bg-muted/60 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Name
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Owner
                                            </th>
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
                                                    {formatDate(
                                                        company.created_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {selectedOperationsTab === 'invites' && (
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
                                            <th className="px-4 py-3 font-medium">
                                                Created
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recentInvites.length === 0 && (
                                            <tr>
                                                <td
                                                    className="px-4 py-8 text-center text-muted-foreground"
                                                    colSpan={5}
                                                >
                                                    No invites found for this
                                                    filter.
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
                                                <td className="px-4 py-3">
                                                    {formatDate(
                                                        invite.created_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {selectedOperationsTab === 'admin_actions' && (
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
                                                    No admin actions found for
                                                    this filter.
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
                                                        {item.company ??
                                                            'Platform'}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {item.actor ?? 'System'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatDate(
                                                        item.created_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
