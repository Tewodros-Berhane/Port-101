import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

type Option = {
    value: string;
    label: string;
};

type QueueRow = {
    queue: string;
    total_jobs: number;
    ready_jobs: number;
    reserved_jobs: number;
    delayed_jobs: number;
    failed_jobs: number;
};

type BreakdownRow = {
    job_name?: string;
    reason?: string;
    company_id?: string;
    company_name?: string;
    count: number;
};

type FailedJobRow = {
    id: string;
    queue: string;
    connection: string;
    job_name: string;
    job_name_label: string;
    request_id?: string | null;
    company_id?: string | null;
    company_name?: string | null;
    user_name?: string | null;
    correlation_origin?: string | null;
    failed_at?: string | null;
    exception_class: string;
    exception_message: string;
    can_retry: boolean;
    can_forget: boolean;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type DeadWebhookDeliveryRow = {
    id: string;
    company_id?: string | null;
    company_name?: string | null;
    endpoint_name?: string | null;
    event_label: string;
    attempt_count: number;
    failure_message?: string | null;
    response_status?: number | null;
    dead_lettered_at?: string | null;
};

type FailedReportExportRow = {
    id: string;
    company_id?: string | null;
    company_name?: string | null;
    report_key: string;
    report_title: string;
    format: string;
    requested_by_name?: string | null;
    failed_at?: string | null;
    failure_message?: string | null;
};

type Props = {
    filters: {
        search: string;
        queue: string;
    };
    summary: {
        queued_jobs: number;
        ready_jobs: number;
        reserved_jobs: number;
        delayed_jobs: number;
        failed_jobs: number;
        failed_jobs_last_24_hours: number;
        dead_webhook_deliveries: number;
        failed_report_exports: number;
        impacted_queues: number;
        impacted_companies: number;
    };
    queueOptions: Option[];
    backlogByQueue: QueueRow[];
    topFailedJobTypes: BreakdownRow[];
    topFailureReasons: BreakdownRow[];
    companyImpact: BreakdownRow[];
    failedJobs: {
        data: FailedJobRow[];
        links: PaginationLink[];
    };
    deadWebhookDeliveries: DeadWebhookDeliveryRow[];
    failedReportExports: FailedReportExportRow[];
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const titleize = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function PlatformQueueHealth({
    filters,
    summary,
    queueOptions,
    backlogByQueue,
    topFailedJobTypes,
    topFailureReasons,
    companyImpact,
    failedJobs,
    deadWebhookDeliveries,
    failedReportExports,
}: Props) {
    const form = useForm({
        search: filters.search,
        queue: filters.queue,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Platform', href: '/platform/dashboard' },
                {
                    title: 'Queue Health',
                    href: '/platform/operations/queue-health',
                },
            ]}
        >
            <Head title="Queue Health" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Queue health</h1>
                        <p className="text-sm text-muted-foreground">
                            Track backlog, failed jobs, dead-letter traffic,
                            and operator retry actions from one platform view.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/platform/dashboard">
                                Platform dashboard
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/platform/reports">Reports</Link>
                        </Button>
                    </div>
                </div>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <MetricCard
                        label="Queued jobs"
                        value={String(summary.queued_jobs)}
                        description={`${summary.ready_jobs} ready now`}
                    />
                    <MetricCard
                        label="Reserved jobs"
                        value={String(summary.reserved_jobs)}
                        description={`${summary.delayed_jobs} delayed`}
                    />
                    <MetricCard
                        label="Failed jobs"
                        value={String(summary.failed_jobs)}
                        description={`${summary.failed_jobs_last_24_hours} in the last 24h`}
                    />
                    <MetricCard
                        label="Dead webhooks"
                        value={String(summary.dead_webhook_deliveries)}
                        description="Dead-letter deliveries needing review"
                    />
                    <MetricCard
                        label="Failed exports"
                        value={String(summary.failed_report_exports)}
                        description={`${summary.impacted_companies} companies impacted`}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                    <div className="rounded-xl border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Queue backlog
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Ready, reserved, delayed, and failed work by
                                    queue.
                                </p>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {summary.impacted_queues} queues impacted
                            </p>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Queue</th>
                                        <th className="px-3 py-2 font-medium">Total</th>
                                        <th className="px-3 py-2 font-medium">Ready</th>
                                        <th className="px-3 py-2 font-medium">Reserved</th>
                                        <th className="px-3 py-2 font-medium">Delayed</th>
                                        <th className="px-3 py-2 font-medium">Failed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {backlogByQueue.length === 0 && (
                                        <tr>
                                            <td className="px-3 py-6 text-sm text-muted-foreground" colSpan={6}>
                                                No queued or failed jobs are currently tracked.
                                            </td>
                                        </tr>
                                    )}
                                    {backlogByQueue.map((row) => (
                                        <tr key={row.queue} className="border-t align-top">
                                            <td className="px-3 py-3 font-medium">{row.queue}</td>
                                            <td className="px-3 py-3">{row.total_jobs}</td>
                                            <td className="px-3 py-3">{row.ready_jobs}</td>
                                            <td className="px-3 py-3">{row.reserved_jobs}</td>
                                            <td className="px-3 py-3">{row.delayed_jobs}</td>
                                            <td className="px-3 py-3">{row.failed_jobs}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="grid gap-6">
                        <BreakdownPanel
                            title="Failed job types"
                            emptyMessage="No failed-job types yet."
                            rows={topFailedJobTypes.map((row) => ({
                                label: row.job_name ?? '-',
                                value: row.count,
                            }))}
                        />
                        <BreakdownPanel
                            title="Failure reasons"
                            emptyMessage="No failure reasons captured."
                            rows={topFailureReasons.map((row) => ({
                                label: row.reason ?? '-',
                                value: row.count,
                            }))}
                        />
                        <BreakdownPanel
                            title="Impacted companies"
                            emptyMessage="No company-specific failures found."
                            rows={companyImpact.map((row) => ({
                                label: row.company_name ?? '-',
                                value: row.count,
                                href: row.company_id ? `/platform/companies/${row.company_id}` : undefined,
                            }))}
                        />
                    </div>
                </section>
                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(220px,0.8fr)_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/platform/operations/queue-health', {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search failed jobs</Label>
                        <Input
                            id="search"
                            value={form.data.search}
                            onChange={(event) => form.setData('search', event.target.value)}
                            placeholder="UUID, queue, exception, payload"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="queue">Queue</Label>
                        <select
                            id="queue"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.queue}
                            onChange={(event) => form.setData('queue', event.target.value)}
                        >
                            <option value="">All queues</option>
                            {queueOptions.map((queueOption) => (
                                <option key={queueOption.value} value={queueOption.value}>
                                    {queueOption.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <Button type="submit">Apply filters</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const reset = { search: '', queue: '' };
                                form.setData(reset);
                                form.get('/platform/operations/queue-health', {
                                    data: reset,
                                    preserveState: true,
                                    replace: true,
                                });
                            }}
                        >
                            Reset
                        </Button>
                    </div>
                </form>

                <section className="rounded-xl border p-5">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">Failed jobs</h2>
                            <p className="text-xs text-muted-foreground">
                                Retry or forget failed queue records without leaving the platform workspace.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Failed at</th>
                                    <th className="px-3 py-2 font-medium">Job</th>
                                    <th className="px-3 py-2 font-medium">Queue</th>
                                    <th className="px-3 py-2 font-medium">Company</th>
                                    <th className="px-3 py-2 font-medium">Request ID</th>
                                    <th className="px-3 py-2 font-medium">Failure</th>
                                    <th className="px-3 py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {failedJobs.data.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-sm text-muted-foreground" colSpan={7}>
                                            No failed jobs match the current filters.
                                        </td>
                                    </tr>
                                )}
                                {failedJobs.data.map((job) => (
                                    <tr key={job.id} className="border-t align-top">
                                        <td className="px-3 py-3 text-xs text-muted-foreground">
                                            {formatDateTime(job.failed_at)}
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="space-y-1">
                                                <p className="font-medium">{job.job_name_label}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {job.connection} / {job.queue}
                                                </p>
                                                <p className="font-mono text-[11px] text-muted-foreground">{job.id}</p>
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">{job.queue}</td>
                                        <td className="px-3 py-3">
                                            {job.company_id ? (
                                                <Link href={`/platform/companies/${job.company_id}`} className="text-sm font-medium hover:underline">
                                                    {job.company_name ?? job.company_id}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-3">
                                            <code className="text-xs">{job.request_id ?? '-'}</code>
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="space-y-1">
                                                <p className="font-medium">{job.exception_class}</p>
                                                <p className="max-w-[320px] text-xs text-muted-foreground">
                                                    {job.exception_message}
                                                </p>
                                                {job.user_name && (
                                                    <p className="text-xs text-muted-foreground">Last actor: {job.user_name}</p>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/platform/operations/queue-health/failed-jobs/${job.id}/retry`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    Retry
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        if (window.confirm('Forget this failed job record?')) {
                                                            router.delete(`/platform/operations/queue-health/failed-jobs/${job.id}`, {
                                                                preserveScroll: true,
                                                            });
                                                        }
                                                    }}
                                                >
                                                    Forget
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {failedJobs.links.length > 0 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {failedJobs.links.map((link) => (
                                <button
                                    key={`${link.label}-${link.url ?? 'null'}`}
                                    type="button"
                                    className={`rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input'
                                    }`}
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.visit(link.url, { preserveScroll: true });
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </section>
                <section className="grid gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">Dead webhook deliveries</h2>
                                <p className="text-xs text-muted-foreground">
                                    Retry dead-letter webhook traffic without opening company workspaces.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            {deadWebhookDeliveries.length === 0 && (
                                <EmptyState message="No dead webhook deliveries." />
                            )}

                            {deadWebhookDeliveries.map((delivery) => (
                                <div key={delivery.id} className="rounded-xl border p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <p className="font-medium">{delivery.event_label}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {delivery.company_id ? (
                                                    <Link href={`/platform/companies/${delivery.company_id}`} className="hover:underline">
                                                        {delivery.company_name ?? delivery.company_id}
                                                    </Link>
                                                ) : (
                                                    'Unknown company'
                                                )}{' '}
                                                | {delivery.endpoint_name ?? 'Unknown endpoint'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Attempts: {delivery.attempt_count}
                                                {delivery.response_status ? ` | HTTP ${delivery.response_status}` : ''}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDateTime(delivery.dead_lettered_at)}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    `/platform/operations/queue-health/webhook-deliveries/${delivery.id}/retry`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Retry
                                        </Button>
                                    </div>

                                    {delivery.failure_message && (
                                        <p className="mt-3 text-xs text-muted-foreground">{delivery.failure_message}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">Failed report exports</h2>
                                <p className="text-xs text-muted-foreground">
                                    Requeue company report exports that failed in background processing.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            {failedReportExports.length === 0 && (
                                <EmptyState message="No failed report exports." />
                            )}

                            {failedReportExports.map((exportRow) => (
                                <div key={exportRow.id} className="rounded-xl border p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <p className="font-medium">{exportRow.report_title}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {exportRow.company_id ? (
                                                    <Link href={`/platform/companies/${exportRow.company_id}`} className="hover:underline">
                                                        {exportRow.company_name ?? exportRow.company_id}
                                                    </Link>
                                                ) : (
                                                    'Unknown company'
                                                )}{' '}
                                                | {titleize(exportRow.format)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Requested by {exportRow.requested_by_name ?? 'Unknown user'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDateTime(exportRow.failed_at)}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    `/platform/operations/queue-health/report-exports/${exportRow.id}/retry`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Retry export
                                        </Button>
                                    </div>

                                    {exportRow.failure_message && (
                                        <p className="mt-3 text-xs text-muted-foreground">{exportRow.failure_message}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
function MetricCard({
    label,
    value,
    description,
}: {
    label: string;
    value: string;
    description?: string;
}) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
            {description && <p className="mt-2 text-xs text-muted-foreground">{description}</p>}
        </div>
    );
}

function BreakdownPanel({
    title,
    rows,
    emptyMessage,
}: {
    title: string;
    rows: {
        label: string;
        value: number;
        href?: string;
    }[];
    emptyMessage: string;
}) {
    return (
        <div className="rounded-xl border p-5">
            <h2 className="text-sm font-semibold">{title}</h2>
            <div className="mt-4 space-y-3">
                {rows.length === 0 && <EmptyState message={emptyMessage} />}
                {rows.map((row) => (
                    <div key={`${title}-${row.label}`} className="flex items-center justify-between gap-3">
                        {row.href ? (
                            <Link href={row.href} className="text-sm font-medium hover:underline">
                                {row.label}
                            </Link>
                        ) : (
                            <p className="text-sm font-medium">{row.label}</p>
                        )}
                        <p className="text-sm text-muted-foreground">{row.value}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return <p className="text-sm text-muted-foreground">{message}</p>;
}
