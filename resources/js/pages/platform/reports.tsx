import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, FileText } from 'lucide-react';

type Props = {
    operationsFilters: {
        trend_window: number;
        admin_action?: string | null;
        admin_actor_id?: string | null;
        admin_start_date?: string | null;
        admin_end_date?: string | null;
        invite_delivery_status?: 'pending' | 'sent' | 'failed' | null;
    };
    adminFilterOptions: {
        actions: string[];
        actors: {
            id: string;
            name: string;
        }[];
    };
    reportCatalog: {
        key: string;
        title: string;
        description: string;
        row_count: number;
    }[];
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
    reportingResearch: Record<
        string,
        {
            title: string;
            source: string;
            url: string;
            summary: string;
        }
    >;
};

const formatDate = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatAction = (action: string) =>
    action.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function PlatformReports({
    operationsFilters,
    adminFilterOptions,
    reportCatalog,
    operationsReportPresets,
    reportingResearch,
}: Props) {
    const form = useForm({
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
        invite_delivery_status: operationsFilters.invite_delivery_status ?? '',
    });
    const presetForm = useForm({
        name: '',
        trend_window: String(operationsFilters.trend_window ?? 30),
        admin_action: operationsFilters.admin_action ?? '',
        admin_actor_id: operationsFilters.admin_actor_id ?? '',
        admin_start_date: operationsFilters.admin_start_date ?? '',
        admin_end_date: operationsFilters.admin_end_date ?? '',
        invite_delivery_status: operationsFilters.invite_delivery_status ?? '',
    });
    const deletePresetForm = useForm({});

    const buildQuery = (
        overrides: Partial<typeof form.data> = {},
        source = form.data,
    ) => {
        const merged = {
            trend_window: source.trend_window,
            admin_action: source.admin_action,
            admin_actor_id: source.admin_actor_id,
            admin_start_date: source.admin_start_date,
            admin_end_date: source.admin_end_date,
            invite_delivery_status: source.invite_delivery_status,
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
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                { title: 'Reports', href: '/platform/reports' },
            ]}
        >
            <Head title="Platform Reports" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Platform reports</h1>
                    <p className="text-sm text-muted-foreground">
                        Centralized reporting for platform performance,
                        operations, admins, invites, and notification events.
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
                    form.get('/platform/reports', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div>
                    <h2 className="text-sm font-semibold">
                        Operations reporting filters
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Apply once and reuse across all report exports.
                    </p>
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
                                form.setData('admin_actor_id', event.target.value)
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
                                form.setData('admin_start_date', event.target.value)
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
                                form.setData('admin_end_date', event.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Apply filters
                    </Button>
                    <Button variant="outline" type="button" asChild>
                        <Link href="/platform/reports">Reset</Link>
                    </Button>
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
                                    trend_window: form.data.trend_window,
                                    admin_action: form.data.admin_action,
                                    admin_actor_id: form.data.admin_actor_id,
                                    admin_start_date: form.data.admin_start_date,
                                    admin_end_date: form.data.admin_end_date,
                                    invite_delivery_status:
                                        form.data.invite_delivery_status,
                                }));

                                presetForm.post('/platform/reports/report-presets', {
                                    preserveScroll: true,
                                    onSuccess: () => presetForm.reset('name'),
                                });
                            }}
                        >
                            Save preset
                        </Button>
                    </div>
                </div>
            </form>

            <div className="mt-6 grid gap-4 md:grid-cols-2">
                {reportCatalog.map((report) => {
                    const reportQuery = buildQuery();
                    const pdfUrl = `/platform/reports/export/${report.key}?${reportQuery}&format=pdf`;
                    const xlsxUrl = `/platform/reports/export/${report.key}?${reportQuery}&format=xlsx`;

                    return (
                        <div
                            key={report.key}
                            className="rounded-xl border bg-card p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold">
                                        {report.title}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {report.description}
                                    </p>
                                </div>
                                <span className="rounded-md border px-2 py-1 text-xs text-muted-foreground">
                                    {report.row_count} rows
                                </span>
                            </div>

                            <div className="mt-4 inline-flex items-center gap-2">
                                <Button variant="outline" asChild>
                                    <a href={pdfUrl}>
                                        <FileText className="size-4" />
                                        PDF
                                    </a>
                                </Button>
                                <Button variant="outline" asChild>
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
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Saved report presets
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Reuse common filter sets for recurring exports.
                        </p>
                    </div>
                    <Download className="size-4 text-muted-foreground" />
                </div>

                <div className="mt-4 overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-max text-sm">
                        <thead className="bg-muted/50 text-left">
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
                            {operationsReportPresets.map((preset) => {
                                const presetQuery = buildQuery(
                                    {
                                        trend_window: String(
                                            preset.filters.trend_window ?? 30,
                                        ),
                                        admin_action:
                                            preset.filters.admin_action ?? '',
                                        admin_actor_id:
                                            preset.filters.admin_actor_id ?? '',
                                        admin_start_date:
                                            preset.filters.admin_start_date ?? '',
                                        admin_end_date:
                                            preset.filters.admin_end_date ?? '',
                                        invite_delivery_status:
                                            preset.filters
                                                .invite_delivery_status ?? '',
                                    },
                                    form.data,
                                );

                                return (
                                    <tr key={preset.id}>
                                        <td className="px-3 py-2 font-medium">
                                            {preset.name}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            Window:{' '}
                                            {preset.filters.trend_window}d |
                                            Action:{' '}
                                            {preset.filters.admin_action
                                                ? formatAction(
                                                      preset.filters.admin_action,
                                                  )
                                                : 'All'}{' '}
                                            | Actor:{' '}
                                            {preset.filters.admin_actor_id
                                                ? 'Specific'
                                                : 'All'}{' '}
                                            | Invite delivery:{' '}
                                            {preset.filters
                                                .invite_delivery_status ?? 'All'}
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
                                                        href={`/platform/reports?${presetQuery}`}
                                                    >
                                                        Apply
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    type="button"
                                                    onClick={() =>
                                                        deletePresetForm.delete(
                                                            `/platform/reports/report-presets/${preset.id}`,
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
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">
                    SaaS reporting research coverage
                </h2>
                <p className="text-xs text-muted-foreground">
                    This report center is aligned to common SaaS KPI guidance
                    for acquisition, retention, and platform operations.
                </p>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                    {Object.values(reportingResearch).map((item) => (
                        <a
                            key={item.url}
                            href={item.url}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-lg border p-3 transition-colors hover:border-primary/40"
                        >
                            <p className="text-sm font-medium">{item.title}</p>
                            <p className="text-xs text-muted-foreground">
                                {item.source}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {item.summary}
                            </p>
                        </a>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
