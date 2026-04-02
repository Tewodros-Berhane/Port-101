import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import GovernanceTimeSeriesChart from '@/components/platform/dashboard/governance-time-series-chart';
import NoisyEventsChart from '@/components/platform/dashboard/noisy-events-chart';
import { FormErrorSummary } from '@/components/shell/form-error-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { platformBreadcrumbs } from '@/lib/page-navigation';

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
        noisy_event_threshold: number;
    };
    notificationGovernanceAnalytics: {
        window_days: number;
        noisy_event_threshold: number;
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
            source: string;
            count: number;
            unread: number;
            high_or_critical: number;
        }[];
        source_segmentation: {
            source: string;
            count: number;
            unread: number;
            high_or_critical: number;
            escalations: number;
        }[];
        time_series: {
            date: string;
            notifications_total: number;
            escalations_triggered: number;
            escalations_acknowledged: number;
            digests_sent: number;
            digests_opened: number;
        }[];
    };
    operationalAlerting: {
        enabled: boolean;
        cooldown_minutes: number;
        failed_jobs_threshold: number;
        queue_backlog_threshold: number;
        dead_webhook_threshold: number;
        failed_report_export_threshold: number;
        scheduler_drift_minutes: number;
    };
    operationalAlertingStatus: {
        last_scan_at?: string | null;
        heartbeat: {
            last_seen_at?: string | null;
            minutes_since?: number | null;
            is_stale: boolean;
        };
        active_incidents: Array<{
            id: string;
            alert_key: string;
            status: string;
            severity: string;
            title: string;
            message: string;
            metric_value?: number | null;
            threshold_value?: number | null;
            first_triggered_at?: string | null;
            last_triggered_at?: string | null;
            last_notified_at?: string | null;
            resolved_at?: string | null;
        }>;
        recent_resolved_incidents: Array<{
            id: string;
            alert_key: string;
            status: string;
            severity: string;
            title: string;
            message: string;
            metric_value?: number | null;
            threshold_value?: number | null;
            resolved_at?: string | null;
        }>;
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
        format: 'pdf' | 'xlsx';
        frequency: 'daily' | 'weekly';
        day_of_week: number;
        time: string;
        timezone: string;
        channels: Array<'in_app' | 'email' | 'webhook' | 'slack'>;
        recipient_mode: 'all_superadmins' | 'selected_superadmins';
        recipient_user_ids: string[];
        additional_emails: string[];
        webhook_url?: string | null;
        slack_webhook_url?: string | null;
        last_sent_at?: string | null;
    };
    platformAdminOptions: {
        id: string;
        name: string;
        email: string;
    }[];
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatPercent = (value: number) => `${value}%`;

const DELIVERY_SCHEDULE_ERROR_LABELS: Record<string, string> = {
    additional_emails: 'Additional recipient emails',
    day_of_week: 'Weekday',
    preset_id: 'Preset',
    recipient_mode: 'Recipients',
    recipient_user_ids: 'Selected platform admins',
    slack_webhook_url: 'Slack webhook URL',
    webhook_url: 'Webhook URL',
};

export default function PlatformGovernance({
    analyticsFilters,
    notificationGovernance,
    notificationGovernanceAnalytics,
    operationalAlerting,
    operationalAlertingStatus,
    operationsReportPresets,
    operationsReportDeliverySchedule,
    platformAdminOptions,
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
        noisy_event_threshold:
            notificationGovernance.noisy_event_threshold ?? 3,
    });
    const alertingForm = useForm({
        enabled: operationalAlerting.enabled ? '1' : '0',
        cooldown_minutes: operationalAlerting.cooldown_minutes ?? 30,
        failed_jobs_threshold:
            operationalAlerting.failed_jobs_threshold ?? 5,
        queue_backlog_threshold:
            operationalAlerting.queue_backlog_threshold ?? 50,
        dead_webhook_threshold:
            operationalAlerting.dead_webhook_threshold ?? 5,
        failed_report_export_threshold:
            operationalAlerting.failed_report_export_threshold ?? 3,
        scheduler_drift_minutes:
            operationalAlerting.scheduler_drift_minutes ?? 10,
    });
    const deliveryScheduleForm = useForm({
        enabled: operationsReportDeliverySchedule.enabled ? '1' : '0',
        preset_id: operationsReportDeliverySchedule.preset_id ?? '',
        format: operationsReportDeliverySchedule.format ?? 'xlsx',
        frequency: operationsReportDeliverySchedule.frequency ?? 'weekly',
        day_of_week: operationsReportDeliverySchedule.day_of_week ?? 1,
        time: operationsReportDeliverySchedule.time ?? '08:00',
        timezone: operationsReportDeliverySchedule.timezone ?? 'UTC',
        channels: operationsReportDeliverySchedule.channels ?? ['in_app'],
        recipient_mode:
            operationsReportDeliverySchedule.recipient_mode ??
            'all_superadmins',
        recipient_user_ids:
            operationsReportDeliverySchedule.recipient_user_ids ?? [],
        additional_emails: (
            operationsReportDeliverySchedule.additional_emails ?? []
        ).join(', '),
        webhook_url: operationsReportDeliverySchedule.webhook_url ?? '',
        slack_webhook_url:
            operationsReportDeliverySchedule.slack_webhook_url ?? '',
    });

    const toggleDeliveryChannel = (
        channel: 'in_app' | 'email' | 'webhook' | 'slack',
    ) => {
        const exists = deliveryScheduleForm.data.channels.includes(channel);
        deliveryScheduleForm.setData(
            'channels',
            exists
                ? deliveryScheduleForm.data.channels.filter(
                      (item) => item !== channel,
                  )
                : [...deliveryScheduleForm.data.channels, channel],
        );
    };

    const toggleRecipientUser = (userId: string) => {
        const exists = deliveryScheduleForm.data.recipient_user_ids.includes(
            userId,
        );
        deliveryScheduleForm.setData(
            'recipient_user_ids',
            exists
                ? deliveryScheduleForm.data.recipient_user_ids.filter(
                      (item) => item !== userId,
                  )
                : [...deliveryScheduleForm.data.recipient_user_ids, userId],
        );
    };

    return (
        <AppLayout
            breadcrumbs={platformBreadcrumbs({ title: 'Governance', href: '/platform/governance' },)}
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
                <BackLinkAction href="/platform/dashboard" label="Back to platform" variant="outline" />
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
                    if (deliveryScheduleForm.data.channels.length === 0) {
                        deliveryScheduleForm.setError(
                            'channels',
                            'Select at least one delivery channel.',
                        );
                        return;
                    }

                    deliveryScheduleForm.clearErrors('channels');
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

                <FormErrorSummary
                    className="mt-4"
                    errors={deliveryScheduleForm.errors}
                    fieldLabels={DELIVERY_SCHEDULE_ERROR_LABELS}
                    title="Review the delivery settings before saving."
                />

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
                        <InputError
                            message={deliveryScheduleForm.errors.preset_id}
                        />
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
                                    event.target.value as 'pdf' | 'xlsx',
                                )
                            }
                        >
                            <option value="pdf">PDF</option>
                            <option value="xlsx">Excel (.xlsx)</option>
                        </select>
                        <InputError
                            message={deliveryScheduleForm.errors.format}
                        />
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
                        <InputError
                            message={deliveryScheduleForm.errors.frequency}
                        />
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
                        <InputError
                            message={deliveryScheduleForm.errors.day_of_week}
                        />
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
                        <InputError
                            message={deliveryScheduleForm.errors.time}
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
                        <InputError
                            message={deliveryScheduleForm.errors.timezone}
                        />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label>Delivery channels</Label>
                        <div className="flex flex-wrap gap-2">
                            {(
                                [
                                    ['in_app', 'In-app'],
                                    ['email', 'Email'],
                                    ['webhook', 'Webhook'],
                                    ['slack', 'Slack'],
                                ] as const
                            ).map(([value, label]) => (
                                <label
                                    key={value}
                                    className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={deliveryScheduleForm.data.channels.includes(
                                            value,
                                        )}
                                        onChange={() =>
                                            toggleDeliveryChannel(value)
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                        {deliveryScheduleForm.errors.channels && (
                            <InputError
                                message={deliveryScheduleForm.errors.channels}
                            />
                        )}
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="recipient_mode">Recipients</Label>
                        <select
                            id="recipient_mode"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={deliveryScheduleForm.data.recipient_mode}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'recipient_mode',
                                    event.target.value as
                                        | 'all_superadmins'
                                        | 'selected_superadmins',
                                )
                            }
                        >
                            <option value="all_superadmins">
                                All platform admins
                            </option>
                            <option value="selected_superadmins">
                                Selected platform admins
                            </option>
                        </select>
                        <InputError
                            message={deliveryScheduleForm.errors.recipient_mode}
                        />
                    </div>

                    {deliveryScheduleForm.data.recipient_mode ===
                        'selected_superadmins' && (
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Selected platform admins</Label>
                            <div className="max-h-40 overflow-y-auto rounded-md border p-2">
                                {platformAdminOptions.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        No platform admins found.
                                    </p>
                                )}
                                <div className="space-y-2">
                                    {platformAdminOptions.map((admin) => (
                                        <label
                                            key={admin.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={deliveryScheduleForm.data.recipient_user_ids.includes(
                                                    admin.id,
                                                )}
                                                onChange={() =>
                                                    toggleRecipientUser(admin.id)
                                                }
                                            />
                                            <span>
                                                {admin.name}{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    ({admin.email})
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            {deliveryScheduleForm.errors.recipient_user_ids && (
                                <InputError
                                    message={
                                        deliveryScheduleForm.errors
                                            .recipient_user_ids
                                    }
                                />
                            )}
                        </div>
                    )}

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="additional_emails">
                            Additional recipient emails
                        </Label>
                        <Input
                            id="additional_emails"
                            value={deliveryScheduleForm.data.additional_emails}
                            onChange={(event) =>
                                deliveryScheduleForm.setData(
                                    'additional_emails',
                                    event.target.value,
                                )
                            }
                            placeholder="ops@example.com, reports@example.com"
                        />
                        <p className="text-xs text-muted-foreground">
                            Optional comma-separated emails for delivery.
                        </p>
                        {deliveryScheduleForm.errors.additional_emails && (
                            <InputError
                                message={
                                    deliveryScheduleForm.errors.additional_emails
                                }
                            />
                        )}
                    </div>

                    {deliveryScheduleForm.data.channels.includes('webhook') && (
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="webhook_url">Webhook URL</Label>
                            <Input
                                id="webhook_url"
                                type="url"
                                value={deliveryScheduleForm.data.webhook_url}
                                onChange={(event) =>
                                    deliveryScheduleForm.setData(
                                        'webhook_url',
                                        event.target.value,
                                    )
                                }
                                placeholder="https://example.com/hooks/ops-report"
                            />
                            {deliveryScheduleForm.errors.webhook_url && (
                                <InputError
                                    message={
                                        deliveryScheduleForm.errors.webhook_url
                                    }
                                />
                            )}
                        </div>
                    )}

                    {deliveryScheduleForm.data.channels.includes('slack') && (
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="slack_webhook_url">
                                Slack webhook URL
                            </Label>
                            <Input
                                id="slack_webhook_url"
                                type="url"
                                value={
                                    deliveryScheduleForm.data.slack_webhook_url
                                }
                                onChange={(event) =>
                                    deliveryScheduleForm.setData(
                                        'slack_webhook_url',
                                        event.target.value,
                                    )
                                }
                                placeholder="https://hooks.slack.com/services/..."
                            />
                            {deliveryScheduleForm.errors.slack_webhook_url && (
                                <InputError
                                    message={
                                        deliveryScheduleForm.errors
                                            .slack_webhook_url
                                    }
                                />
                            )}
                        </div>
                    )}

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
                        <Link href="/platform/reports">Manage presets</Link>
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

                    <div className="grid gap-2">
                        <Label htmlFor="noisy_event_threshold">
                            Noisy-event threshold
                        </Label>
                        <Input
                            id="noisy_event_threshold"
                            type="number"
                            min={1}
                            max={100}
                            value={String(
                                governanceForm.data.noisy_event_threshold,
                            )}
                            onChange={(event) =>
                                governanceForm.setData(
                                    'noisy_event_threshold',
                                    Number(event.target.value || 1),
                                )
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Minimum event count required to appear in noisy-event
                            analytics.
                        </p>
                    </div>
                </div>

                <div className="mt-4">
                    <Button type="submit" disabled={governanceForm.processing}>
                        Save governance controls
                    </Button>
                </div>
            </form>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    alertingForm.put('/platform/dashboard/operational-alerting', {
                        preserveScroll: true,
                    });
                }}
            >
                <div>
                    <h2 className="text-sm font-semibold">
                        Operational alerting
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Trigger platform alerts for queue failures, backlog,
                        dead webhooks, failed exports, and stale scheduler
                        heartbeats.
                    </p>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="alerting_enabled">Alerting</Label>
                        <select
                            id="alerting_enabled"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={alertingForm.data.enabled}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'enabled',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="cooldown_minutes">
                            Cooldown (minutes)
                        </Label>
                        <Input
                            id="cooldown_minutes"
                            type="number"
                            min={1}
                            max={1440}
                            value={String(alertingForm.data.cooldown_minutes)}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'cooldown_minutes',
                                    Number(event.target.value || 1),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="failed_jobs_threshold">
                            Failed jobs threshold
                        </Label>
                        <Input
                            id="failed_jobs_threshold"
                            type="number"
                            min={0}
                            max={100000}
                            value={String(
                                alertingForm.data.failed_jobs_threshold,
                            )}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'failed_jobs_threshold',
                                    Number(event.target.value || 0),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="queue_backlog_threshold">
                            Ready backlog threshold
                        </Label>
                        <Input
                            id="queue_backlog_threshold"
                            type="number"
                            min={0}
                            max={100000}
                            value={String(
                                alertingForm.data.queue_backlog_threshold,
                            )}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'queue_backlog_threshold',
                                    Number(event.target.value || 0),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="dead_webhook_threshold">
                            Dead webhook threshold
                        </Label>
                        <Input
                            id="dead_webhook_threshold"
                            type="number"
                            min={0}
                            max={100000}
                            value={String(
                                alertingForm.data.dead_webhook_threshold,
                            )}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'dead_webhook_threshold',
                                    Number(event.target.value || 0),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="failed_report_export_threshold">
                            Failed exports threshold
                        </Label>
                        <Input
                            id="failed_report_export_threshold"
                            type="number"
                            min={0}
                            max={100000}
                            value={String(
                                alertingForm.data.failed_report_export_threshold,
                            )}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'failed_report_export_threshold',
                                    Number(event.target.value || 0),
                                )
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="scheduler_drift_minutes">
                            Scheduler drift (minutes)
                        </Label>
                        <Input
                            id="scheduler_drift_minutes"
                            type="number"
                            min={1}
                            max={1440}
                            value={String(
                                alertingForm.data.scheduler_drift_minutes,
                            )}
                            onChange={(event) =>
                                alertingForm.setData(
                                    'scheduler_drift_minutes',
                                    Number(event.target.value || 1),
                                )
                            }
                        />
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-4">
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Last scan
                        </p>
                        <p className="mt-1 text-sm font-semibold">
                            {formatDate(operationalAlertingStatus.last_scan_at)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Scheduler heartbeat
                        </p>
                        <p className="mt-1 text-sm font-semibold">
                            {formatDate(
                                operationalAlertingStatus.heartbeat.last_seen_at,
                            )}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {operationalAlertingStatus.heartbeat.minutes_since !==
                            null
                                ? `${operationalAlertingStatus.heartbeat.minutes_since} minute(s) ago`
                                : 'No heartbeat recorded'}
                        </p>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Active alerts
                        </p>
                        <p className="mt-1 text-xl font-semibold">
                            {operationalAlertingStatus.active_incidents.length}
                        </p>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Heartbeat state
                        </p>
                        <p className="mt-1 text-sm font-semibold">
                            {operationalAlertingStatus.heartbeat.is_stale
                                ? 'Stale'
                                : 'Healthy'}
                        </p>
                    </div>
                </div>

                <div className="mt-4 space-y-3">
                    <div>
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Active incidents
                        </h3>
                    </div>
                    {operationalAlertingStatus.active_incidents.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No active operational alerts.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            {operationalAlertingStatus.active_incidents.map(
                                (incident) => (
                                    <div
                                        key={incident.id}
                                        className="rounded-lg border p-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {incident.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {incident.severity.toUpperCase()}{' '}
                                                    · Triggered{' '}
                                                    {formatDate(
                                                        incident.first_triggered_at,
                                                    )}
                                                </p>
                                            </div>
                                            <p className="text-sm font-medium">
                                                {incident.metric_value ?? 0} /{' '}
                                                {incident.threshold_value ?? 0}
                                            </p>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {incident.message}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    )}
                </div>

                <div className="mt-4">
                    <Button type="submit" disabled={alertingForm.processing}>
                        Save operational alerting
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

                <div className="mt-4 grid gap-4 md:grid-cols-4">
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
                    <div className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">
                            Noisy-event threshold
                        </p>
                        <p className="mt-1 text-xl font-semibold">
                            {
                                notificationGovernanceAnalytics.noisy_event_threshold
                            }
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Events must meet this count to appear in noisy-event
                            drill-downs.
                        </p>
                    </div>
                </div>

                <div className="mt-4 rounded-md border p-4">
                    <div className="mb-3">
                        <h3 className="text-sm font-semibold">
                            Time-series trend
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Daily escalation and digest activity over the selected
                            analytics window.
                        </p>
                    </div>
                    <GovernanceTimeSeriesChart
                        rows={notificationGovernanceAnalytics.time_series}
                    />
                </div>

                <div className="mt-4 rounded-md border p-4">
                    <div className="mb-3">
                        <h3 className="text-sm font-semibold">
                            Source segmentation
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Notification distribution grouped by source channel.
                        </p>
                    </div>
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full min-w-[680px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Source
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Notifications
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Unread
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        High/Critical
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Escalations
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {notificationGovernanceAnalytics
                                    .source_segmentation.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No source-segmentation data for this
                                            window.
                                        </td>
                                    </tr>
                                )}
                                {notificationGovernanceAnalytics.source_segmentation.map(
                                    (row) => (
                                        <tr key={row.source}>
                                            <td className="px-3 py-2 font-medium">
                                                {row.source}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.count}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.unread}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.high_or_critical}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.escalations}
                                            </td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto rounded-md border p-4">
                    <div className="mb-3">
                        <h3 className="text-sm font-semibold">Noisy events</h3>
                        <p className="text-xs text-muted-foreground">
                            Event and source combinations at or above threshold (
                            {
                                notificationGovernanceAnalytics.noisy_event_threshold
                            }
                            ).
                        </p>
                    </div>
                    <NoisyEventsChart
                        rows={notificationGovernanceAnalytics.noisy_events}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
