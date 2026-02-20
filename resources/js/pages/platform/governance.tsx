import NoisyEventsChart from '@/components/platform/dashboard/noisy-events-chart';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    analyticsFilters: {
        trend_window: number;
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
    operationsReportDeliverySchedule: {
        enabled: boolean;
        preset_id?: string | null;
        format: 'csv' | 'json';
        frequency: 'daily' | 'weekly';
        day_of_week: number;
        time: string;
        timezone: string;
        last_sent_at?: string | null;
    };
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatPercent = (value: number) => `${value}%`;

export default function PlatformGovernance({
    analyticsFilters,
    notificationGovernance,
    notificationGovernanceAnalytics,
    operationsReportPresets,
    operationsReportDeliverySchedule,
}: Props) {
    const analyticsForm = useForm({
        trend_window: String(analyticsFilters.trend_window ?? 30),
    });
    const governanceForm = useForm({
        min_severity: notificationGovernance.min_severity ?? 'low',
        escalation_enabled: notificationGovernance.escalation_enabled
            ? '1'
            : '0',
        escalation_severity:
            notificationGovernance.escalation_severity ?? 'high',
        escalation_delay_minutes:
            notificationGovernance.escalation_delay_minutes ?? 30,
        digest_enabled: notificationGovernance.digest_enabled ? '1' : '0',
        digest_frequency: notificationGovernance.digest_frequency ?? 'daily',
        digest_day_of_week: notificationGovernance.digest_day_of_week ?? 1,
        digest_time: notificationGovernance.digest_time ?? '08:00',
        digest_timezone: notificationGovernance.digest_timezone ?? 'UTC',
    });
    const deliveryScheduleForm = useForm({
        enabled: operationsReportDeliverySchedule.enabled ? '1' : '0',
        preset_id: operationsReportDeliverySchedule.preset_id ?? '',
        format: operationsReportDeliverySchedule.format ?? 'csv',
        frequency: operationsReportDeliverySchedule.frequency ?? 'weekly',
        day_of_week: operationsReportDeliverySchedule.day_of_week ?? 1,
        time: operationsReportDeliverySchedule.time ?? '08:00',
        timezone: operationsReportDeliverySchedule.timezone ?? 'UTC',
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Governance', href: '/platform/governance' },
            ]}
        >
            <Head title="Platform Governance" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Platform governance
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Configure delivery policies, escalation behavior, and
                        digest governance.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href="/platform/dashboard">Back to dashboard</Link>
                </Button>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    analyticsForm.get('/platform/governance', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid min-w-[220px] gap-2">
                        <Label htmlFor="analytics_trend_window">
                            Analytics window
                        </Label>
                        <select
                            id="analytics_trend_window"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={analyticsForm.data.trend_window}
                            onChange={(event) =>
                                analyticsForm.setData(
                                    'trend_window',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                    <Button type="submit" disabled={analyticsForm.processing}>
                        Refresh analytics
                    </Button>
                </div>
            </form>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    deliveryScheduleForm.put(
                        '/platform/dashboard/report-delivery-schedule',
                        {
                            preserveScroll: true,
                        },
                    );
                }}
            >
                <div>
                    <h2 className="text-sm font-semibold">
                        Scheduled export delivery
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Send operations reports to platform admins on a
                        schedule.
                    </p>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="delivery_enabled">Delivery</Label>
                        <select
                            id="delivery_enabled"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={deliveryScheduleForm.data.enabled}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'enabled',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="0">Disabled</option>
                            <option value="1">Enabled</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_preset">Preset</Label>
                        <select
                            id="delivery_preset"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={deliveryScheduleForm.data.preset_id}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'preset_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Use current defaults</option>
                            {operationsReportPresets.map((preset) => (
                                <option key={preset.id} value={preset.id}>
                                    {preset.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_format">Export format</Label>
                        <select
                            id="delivery_format"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={deliveryScheduleForm.data.format}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'format',
                                    event.target.value as 'csv' | 'json',
                                )
                            }
                        >
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_frequency">Frequency</Label>
                        <select
                            id="delivery_frequency"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={deliveryScheduleForm.data.frequency}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'frequency',
                                    event.target.value as 'daily' | 'weekly',
                                )
                            }
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_day_of_week">
                            Weekday (weekly)
                        </Label>
                        <select
                            id="delivery_day_of_week"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={String(
                                deliveryScheduleForm.data.day_of_week,
                            )}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'day_of_week',
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
                        <Label htmlFor="delivery_time">Time</Label>
                        <Input
                            id="delivery_time"
                            type="time"
                            value={deliveryScheduleForm.data.time}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'time',
                                    event.target.value,
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="delivery_timezone">Timezone</Label>
                        <Input
                            id="delivery_timezone"
                            value={deliveryScheduleForm.data.timezone}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'timezone',
                                    event.target.value,
                                )
                            }
                            placeholder="UTC"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label>Last delivery</Label>
                        <p className="rounded-md border px-3 py-2 text-sm text-muted-foreground">
                            {formatDate(
                                operationsReportDeliverySchedule.last_sent_at ??
                                    null,
                            )}
                        </p>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        disabled={deliveryScheduleForm.processing}
                    >
                        Save delivery schedule
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/platform/dashboard">Manage presets</Link>
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
                <div>
                    <h2 className="text-sm font-semibold">
                        Notification governance controls
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Set minimum severity, escalation thresholds, and digest
                        cadence.
                    </p>
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
                        <Label htmlFor="escalation_enabled">Escalation</Label>
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
                            value={String(
                                governanceForm.data.digest_day_of_week,
                            )}
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

                <div className="mt-4">
                    <Button type="submit" disabled={governanceForm.processing}>
                        Save governance controls
                    </Button>
                </div>
            </form>

            <div className="mt-6 rounded-xl border p-4">
                <div>
                    <h2 className="text-sm font-semibold">
                        Governance analytics snapshot
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Escalation outcomes and digest engagement for this
                        window ({notificationGovernanceAnalytics.window_days}{' '}
                        days).
                    </p>
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

                <div className="mt-4 overflow-x-auto rounded-md border p-4">
                    <NoisyEventsChart
                        rows={notificationGovernanceAnalytics.noisy_events}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
