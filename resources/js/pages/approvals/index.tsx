import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

type ApprovalRequestRow = {
    id: string;
    module: string;
    action: string;
    source_type: string;
    source_number?: string | null;
    status: string;
    amount?: number | null;
    currency_code?: string | null;
    risk_level?: string | null;
    requested_by?: string | null;
    approved_by?: string | null;
    rejected_by?: string | null;
    requested_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
    can_approve: boolean;
};

type Props = {
    filters: {
        status?: string | null;
        module?: string | null;
        start_date?: string | null;
        end_date?: string | null;
    };
    metrics: {
        pending: number;
        approved_30d: number;
        rejected_30d: number;
        approval_rate_30d: number;
        avg_turnaround_hours_30d: number;
    };
    approvalRequests: {
        data: ApprovalRequestRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatLabel = (value: string) =>
    value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

export default function ApprovalsIndex({ filters, metrics, approvalRequests }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('approvals.requests.manage');

    const form = useForm({
        status: filters.status ?? '',
        module: filters.module ?? '',
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
    });

    const handleApprove = (requestId: string) => {
        router.post(`/company/approvals/${requestId}/approve`, {}, { preserveScroll: true });
    };

    const handleReject = (requestId: string) => {
        const reason = window.prompt('Optional rejection reason') ?? '';

        router.post(
            `/company/approvals/${requestId}/reject`,
            { reason },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Approvals', href: '/company/approvals' },
            ]}
        >
            <Head title="Approvals" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Approvals queue</h1>
                    <p className="text-sm text-muted-foreground">
                        Unified review queue for cross-module approval requests.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href="/company/reports">Open reports</Link>
                </Button>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <MetricCard label="Pending" value={String(metrics.pending)} />
                <MetricCard
                    label="Approved (30d)"
                    value={String(metrics.approved_30d)}
                />
                <MetricCard
                    label="Rejected (30d)"
                    value={String(metrics.rejected_30d)}
                />
                <MetricCard
                    label="Approval rate"
                    value={`${metrics.approval_rate_30d.toFixed(2)}%`}
                />
                <MetricCard
                    label="Avg turnaround"
                    value={`${metrics.avg_turnaround_hours_30d.toFixed(2)}h`}
                />
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/company/approvals', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-4 md:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
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
                        <Label htmlFor="module">Module</Label>
                        <select
                            id="module"
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.module}
                            onChange={(event) =>
                                form.setData('module', event.target.value)
                            }
                        >
                            <option value="">All modules</option>
                            <option value="sales">Sales</option>
                            <option value="purchasing">Purchasing</option>
                            <option value="inventory">Inventory</option>
                            <option value="accounting">Accounting</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="start_date">Start date</Label>
                        <Input
                            id="start_date"
                            type="date"
                            value={form.data.start_date}
                            onChange={(event) =>
                                form.setData('start_date', event.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="end_date">End date</Label>
                        <Input
                            id="end_date"
                            type="date"
                            value={form.data.end_date}
                            onChange={(event) =>
                                form.setData('end_date', event.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Apply filters
                    </Button>
                    <Button variant="outline" type="button" asChild>
                        <Link href="/company/approvals">Reset</Link>
                    </Button>
                </div>
            </form>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1240px] text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-3 py-2 font-medium">Reference</th>
                            <th className="px-3 py-2 font-medium">Module</th>
                            <th className="px-3 py-2 font-medium">Action</th>
                            <th className="px-3 py-2 font-medium">Status</th>
                            <th className="px-3 py-2 font-medium">Amount</th>
                            <th className="px-3 py-2 font-medium">Risk</th>
                            <th className="px-3 py-2 font-medium">Requested by</th>
                            <th className="px-3 py-2 font-medium">Requested at</th>
                            <th className="px-3 py-2 font-medium">Decision</th>
                            <th className="px-3 py-2 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {approvalRequests.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-3 py-6 text-center text-muted-foreground"
                                    colSpan={10}
                                >
                                    No approval requests found.
                                </td>
                            </tr>
                        )}
                        {approvalRequests.data.map((approvalRequest) => {
                            const decisionBy =
                                approvalRequest.status === 'approved'
                                    ? approvalRequest.approved_by
                                    : approvalRequest.status === 'rejected'
                                      ? approvalRequest.rejected_by
                                      : null;
                            const decisionAt =
                                approvalRequest.status === 'approved'
                                    ? approvalRequest.approved_at
                                    : approvalRequest.status === 'rejected'
                                      ? approvalRequest.rejected_at
                                      : null;

                            return (
                                <tr key={approvalRequest.id}>
                                    <td className="px-3 py-2 font-medium">
                                        {approvalRequest.source_number ?? '-'}
                                        <p className="text-xs text-muted-foreground">
                                            {approvalRequest.source_type}
                                        </p>
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {approvalRequest.module}
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatLabel(approvalRequest.action)}
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {approvalRequest.status}
                                    </td>
                                    <td className="px-3 py-2">
                                        {approvalRequest.amount !== null &&
                                        approvalRequest.amount !== undefined
                                            ? `${approvalRequest.currency_code ?? ''} ${approvalRequest.amount.toFixed(2)}`
                                            : '-'}
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {approvalRequest.risk_level ?? '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {approvalRequest.requested_by ?? '-'}
                                    </td>
                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                        {formatDateTime(approvalRequest.requested_at)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {decisionBy ? (
                                            <>
                                                <p>{decisionBy}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(decisionAt)}
                                                </p>
                                                {approvalRequest.rejection_reason && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {approvalRequest.rejection_reason}
                                                    </p>
                                                )}
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {canManage && approvalRequest.status === 'pending' ? (
                                            <div className="inline-flex items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    type="button"
                                                    onClick={() =>
                                                        handleApprove(
                                                            approvalRequest.id,
                                                        )
                                                    }
                                                    disabled={
                                                        !approvalRequest.can_approve
                                                    }
                                                >
                                                    Approve
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    type="button"
                                                    onClick={() =>
                                                        handleReject(
                                                            approvalRequest.id,
                                                        )
                                                    }
                                                    disabled={
                                                        !approvalRequest.can_approve
                                                    }
                                                >
                                                    Reject
                                                </Button>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                {approvalRequest.status ===
                                                    'pending' &&
                                                !approvalRequest.can_approve
                                                    ? 'No authority'
                                                    : 'Closed'}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {approvalRequests.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {approvalRequests.links.map((link) => (
                        <Link
                            key={link.label}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1 text-sm ${
                                link.active
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
