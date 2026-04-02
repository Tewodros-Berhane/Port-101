import { Head, Link, useForm } from '@inertiajs/react';
import { FileSpreadsheet, FileText } from 'lucide-react';
import InputError from '@/components/input-error';
import { FormErrorSummary } from '@/components/shell/form-error-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { normalizeFormErrors } from '@/lib/form-feedback';
import { companyBreadcrumbs } from '@/lib/page-navigation';

type ReportCatalogItem = {
    key: string;
    title: string;
    description: string;
    row_count: number;
};

type ReportPreset = {
    id: string;
    name: string;
    filters: {
        trend_window: number;
        start_date?: string | null;
        end_date?: string | null;
        approval_status?: string | null;
    };
    created_at?: string | null;
};

type DeliverySchedule = {
    enabled: boolean;
    preset_id?: string | null;
    report_key: string;
    format: 'pdf' | 'xlsx';
    frequency: 'daily' | 'weekly';
    day_of_week: number;
    time: string;
    timezone: string;
    last_sent_at?: string | null;
};

type Props = {
    filters: {
        trend_window: number;
        start_date?: string | null;
        end_date?: string | null;
        approval_status?: string | null;
    };
    reportCatalog: ReportCatalogItem[];
    reportPresets: ReportPreset[];
    deliverySchedule: DeliverySchedule;
    reportKeyOptions: {
        value: string;
        label: string;
    }[];
    canExport: boolean;
    canManage: boolean;
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const REPORT_DELIVERY_ERROR_LABELS: Record<string, string> = {
    day_of_week: 'Day of week',
    preset_id: 'Preset',
    report_key: 'Report',
};

export default function CompanyReportsIndex({
    filters,
    reportCatalog,
    reportPresets,
    deliverySchedule,
    reportKeyOptions,
    canExport,
    canManage,
}: Props) {
    const filterForm = useForm({
        trend_window: String(filters.trend_window ?? 30),
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
        approval_status: filters.approval_status ?? '',
    });

    const presetForm = useForm({
        name: '',
        trend_window: String(filters.trend_window ?? 30),
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
        approval_status: filters.approval_status ?? '',
    });

    const deletePresetForm = useForm({});

    const scheduleForm = useForm({
        enabled: deliverySchedule.enabled,
        preset_id: deliverySchedule.preset_id ?? '',
        report_key: deliverySchedule.report_key,
        format: deliverySchedule.format,
        frequency: deliverySchedule.frequency,
        day_of_week: String(deliverySchedule.day_of_week),
        time: deliverySchedule.time,
        timezone: deliverySchedule.timezone,
    });
    const scheduleErrors = normalizeFormErrors(scheduleForm.errors, {
        prefix: 'delivery_schedule',
    });

    const buildQuery = (
        overrides: Partial<typeof filterForm.data> = {},
        source = filterForm.data,
    ) => {
        const merged = {
            trend_window: source.trend_window,
            start_date: source.start_date,
            end_date: source.end_date,
            approval_status: source.approval_status,
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

    return (
        <AppLayout
            breadcrumbs={companyBreadcrumbs({ title: 'Reports', href: '/company/reports' })}
        >
            <Head title="Reports" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Reports center</h1>
                    <p className="text-sm text-muted-foreground">
                        Operational and financial exports with reusable presets.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href="/company/approvals">Open approvals queue</Link>
                </Button>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    filterForm.get('/company/reports', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-4 md:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="trend_window">Trend window</Label>
                        <select
                            id="trend_window"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filterForm.data.trend_window}
                            onChange={(event) =>
                                filterForm.setData(
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

                    <div className="grid gap-2">
                        <Label htmlFor="approval_status">Approval status</Label>
                        <select
                            id="approval_status"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filterForm.data.approval_status}
                            onChange={(event) =>
                                filterForm.setData(
                                    'approval_status',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="start_date">Start date</Label>
                        <Input
                            id="start_date"
                            type="date"
                            value={filterForm.data.start_date}
                            onChange={(event) =>
                                filterForm.setData('start_date', event.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="end_date">End date</Label>
                        <Input
                            id="end_date"
                            type="date"
                            value={filterForm.data.end_date}
                            onChange={(event) =>
                                filterForm.setData('end_date', event.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button type="submit" disabled={filterForm.processing}>
                        Apply filters
                    </Button>
                    <Button variant="outline" type="button" asChild>
                        <Link href="/company/reports">Reset</Link>
                    </Button>
                    {canManage && (
                        <div className="flex min-w-[280px] flex-1 items-center gap-2">
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
                                        trend_window: filterForm.data.trend_window,
                                        start_date: filterForm.data.start_date,
                                        end_date: filterForm.data.end_date,
                                        approval_status:
                                            filterForm.data.approval_status,
                                    }));

                                    presetForm.post('/company/reports/presets', {
                                        preserveScroll: true,
                                        onSuccess: () => presetForm.reset('name'),
                                    });
                                }}
                            >
                                Save preset
                            </Button>
                        </div>
                    )}
                </div>
            </form>

            <div className="mt-6 grid gap-4 md:grid-cols-2">
                {reportCatalog.map((report) => {
                    const query = buildQuery();
                    const pdfUrl = `/company/reports/export/${report.key}?${query}&format=pdf`;
                    const xlsxUrl = `/company/reports/export/${report.key}?${query}&format=xlsx`;

                    return (
                        <div key={report.key} className="rounded-xl border bg-card p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold">{report.title}</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {report.description}
                                    </p>
                                </div>
                                <span className="rounded-md border px-2 py-1 text-xs text-muted-foreground">
                                    {report.row_count} rows
                                </span>
                            </div>

                            <div className="mt-4 inline-flex items-center gap-2">
                                <Button variant="outline" asChild disabled={!canExport}>
                                    <a href={pdfUrl}>
                                        <FileText className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                                <Button variant="outline" asChild disabled={!canExport}>
                                    <a href={xlsxUrl}>
                                        <FileSpreadsheet className="size-4" />
                                        Excel
                                    </a>
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">Saved presets</h2>
                <p className="text-xs text-muted-foreground">
                    Apply recurring filter sets for one-click report exports.
                </p>

                <div className="mt-4 overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[860px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Preset</th>
                                <th className="px-3 py-2 font-medium">Filters</th>
                                <th className="px-3 py-2 font-medium">Created</th>
                                <th className="px-3 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {reportPresets.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No presets yet.
                                    </td>
                                </tr>
                            )}

                            {reportPresets.map((preset) => {
                                const presetQuery = buildQuery({
                                    trend_window: String(
                                        preset.filters.trend_window ?? 30,
                                    ),
                                    start_date: preset.filters.start_date ?? '',
                                    end_date: preset.filters.end_date ?? '',
                                    approval_status:
                                        preset.filters.approval_status ?? '',
                                });

                                return (
                                    <tr key={preset.id}>
                                        <td className="px-3 py-2 font-medium">
                                            {preset.name}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            Window {preset.filters.trend_window}d | Start{' '}
                                            {preset.filters.start_date ?? '-'} | End{' '}
                                            {preset.filters.end_date ?? '-'} | Approval{' '}
                                            {preset.filters.approval_status ?? 'All'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {formatDate(preset.created_at)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="inline-flex items-center gap-2">
                                                <Button variant="outline" asChild>
                                                    <Link
                                                        href={`/company/reports?preset_id=${preset.id}&${presetQuery}`}
                                                    >
                                                        Apply
                                                    </Link>
                                                </Button>
                                                {canManage && (
                                                    <Button
                                                        variant="destructive"
                                                        type="button"
                                                        onClick={() =>
                                                            deletePresetForm.delete(
                                                                `/company/reports/presets/${preset.id}`,
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
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">Scheduled report delivery</h2>
                <p className="text-xs text-muted-foreground">
                    Auto-deliver selected exports to report recipients by schedule.
                </p>

                <FormErrorSummary
                    className="mt-4"
                    errors={scheduleErrors}
                    fieldLabels={REPORT_DELIVERY_ERROR_LABELS}
                    title="Review the report delivery schedule before saving."
                />

                <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="schedule_enabled">Enabled</Label>
                        <select
                            id="schedule_enabled"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={scheduleForm.data.enabled ? '1' : '0'}
                            onChange={(event) =>
                                scheduleForm.setData(
                                    'enabled',
                                    event.target.value === '1',
                                )
                            }
                        >
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_preset">Preset</Label>
                        <select
                            id="schedule_preset"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={scheduleForm.data.preset_id}
                            onChange={(event) =>
                                scheduleForm.setData('preset_id', event.target.value)
                            }
                        >
                            <option value="">Default filters</option>
                            {reportPresets.map((preset) => (
                                <option key={preset.id} value={preset.id}>
                                    {preset.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={scheduleErrors.preset_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_report_key">Report</Label>
                        <select
                            id="schedule_report_key"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={scheduleForm.data.report_key}
                            onChange={(event) =>
                                scheduleForm.setData('report_key', event.target.value)
                            }
                        >
                            {reportKeyOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={scheduleErrors.report_key} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_format">Format</Label>
                        <select
                            id="schedule_format"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={scheduleForm.data.format}
                            onChange={(event) =>
                                scheduleForm.setData(
                                    'format',
                                    event.target.value as 'pdf' | 'xlsx',
                                )
                            }
                        >
                            <option value="pdf">PDF</option>
                            <option value="xlsx">Excel</option>
                        </select>
                        <InputError message={scheduleErrors.format} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_frequency">Frequency</Label>
                        <select
                            id="schedule_frequency"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={scheduleForm.data.frequency}
                            onChange={(event) =>
                                scheduleForm.setData(
                                    'frequency',
                                    event.target.value as 'daily' | 'weekly',
                                )
                            }
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                        <InputError message={scheduleErrors.frequency} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_day_of_week">Day of week</Label>
                        <select
                            id="schedule_day_of_week"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={scheduleForm.data.day_of_week}
                            onChange={(event) =>
                                scheduleForm.setData(
                                    'day_of_week',
                                    event.target.value,
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
                        <InputError message={scheduleErrors.day_of_week} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_time">Time</Label>
                        <Input
                            id="schedule_time"
                            type="time"
                            value={scheduleForm.data.time}
                            onChange={(event) =>
                                scheduleForm.setData('time', event.target.value)
                            }
                        />
                        <InputError message={scheduleErrors.time} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="schedule_timezone">Timezone</Label>
                        <Input
                            id="schedule_timezone"
                            value={scheduleForm.data.timezone}
                            onChange={(event) =>
                                scheduleForm.setData('timezone', event.target.value)
                            }
                            placeholder="UTC"
                        />
                        <InputError message={scheduleErrors.timezone} />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        disabled={scheduleForm.processing || !canManage}
                        onClick={() => {
                            scheduleForm.transform((data) => ({
                                ...data,
                                day_of_week: Number(data.day_of_week),
                            }));

                            scheduleForm.put('/company/reports/delivery-schedule', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        Save schedule
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Last sent: {formatDate(deliverySchedule.last_sent_at)}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
