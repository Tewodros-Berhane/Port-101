import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ReasonDialog } from '@/components/feedback/reason-dialog';
import { DataTableShell } from '@/components/shell/data-table-shell';
import {
    FilterField,
    FilterToolbar,
    FilterToolbarActions,
    FilterToolbarGrid,
} from '@/components/shell/filter-toolbar';
import { KpiStrip, MetricCard } from '@/components/shell/kpi-strip';
import { PageHeader } from '@/components/shell/page-header';
import { PaginationBar } from '@/components/shell/pagination-bar';
import { WorkspaceShell } from '@/components/shell/workspace-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyBreadcrumbs } from '@/lib/page-navigation';

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

const riskVariant = (riskLevel?: string | null) => {
    switch (riskLevel) {
        case 'high':
        case 'critical':
            return 'danger';
        case 'medium':
            return 'warning';
        case 'low':
            return 'info';
        default:
            return 'outline';
    }
};

export default function ApprovalsIndex({
    filters,
    metrics,
    approvalRequests,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('approvals.requests.manage');
    const [rejectingRequestId, setRejectingRequestId] = useState<string | null>(
        null,
    );

    const form = useForm({
        status: filters.status ?? '',
        module: filters.module ?? '',
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
    });
    const rejectForm = useForm({
        reason: '',
    });
    const rejectErrors = rejectForm.errors as Record<string, string | undefined>;
    const rejectingRequest = useMemo(
        () =>
            approvalRequests.data.find(
                (approvalRequest) => approvalRequest.id === rejectingRequestId,
            ) ?? null,
        [approvalRequests.data, rejectingRequestId],
    );

    const handleApprove = (requestId: string) => {
        router.post(`/company/approvals/${requestId}/approve`, {}, { preserveScroll: true });
    };

    const handleRejectOpenChange = (open: boolean) => {
        if (rejectForm.processing) {
            return;
        }

        if (!open) {
            setRejectingRequestId(null);
            rejectForm.reset();
            rejectForm.clearErrors();
        }
    };

    const handleReject = () => {
        if (!rejectingRequest) {
            return;
        }

        rejectForm.post(`/company/approvals/${rejectingRequest.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                handleRejectOpenChange(false);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={companyBreadcrumbs({ title: 'Approvals', href: '/company/approvals' })}
        >
            <Head title="Approvals" />

            <WorkspaceShell
                header={
                    <PageHeader
                        title="Approvals queue"
                        description="Unified review queue for cross-module approval requests."
                        actions={
                            <Button variant="outline" asChild>
                                <Link href="/company/reports">Open reports</Link>
                            </Button>
                        }
                        meta={
                            <>
                                <span>{metrics.pending} pending actions</span>
                                <span className="h-1 w-1 rounded-full bg-[color:var(--text-muted)]" />
                                <span>{approvalRequests.data.length} rows on this page</span>
                            </>
                        }
                    />
                }
                kpis={
                    <KpiStrip className="xl:grid-cols-5">
                        <MetricCard label="Pending" value={String(metrics.pending)} tone="warning" />
                        <MetricCard
                            label="Approved (30d)"
                            value={String(metrics.approved_30d)}
                            tone="success"
                        />
                        <MetricCard
                            label="Rejected (30d)"
                            value={String(metrics.rejected_30d)}
                            tone="danger"
                        />
                        <MetricCard
                            label="Approval rate"
                            value={`${metrics.approval_rate_30d.toFixed(2)}%`}
                            tone="info"
                        />
                        <MetricCard
                            label="Avg turnaround"
                            value={`${metrics.avg_turnaround_hours_30d.toFixed(2)}h`}
                        />
                    </KpiStrip>
                }
                table={
                    <DataTableShell>
                        <Table container={false} className="min-w-[1240px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Module</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Risk</TableHead>
                                    <TableHead>Requested by</TableHead>
                                    <TableHead>Requested at</TableHead>
                                    <TableHead>Decision</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {approvalRequests.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            className="py-12 text-center text-sm text-muted-foreground"
                                            colSpan={10}
                                        >
                                            No approval requests found.
                                        </TableCell>
                                    </TableRow>
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
                                        <TableRow key={approvalRequest.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {approvalRequest.source_number ?? '-'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {approvalRequest.source_type}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="capitalize">
                                                    {approvalRequest.module}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {formatLabel(approvalRequest.action)}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={approvalRequest.status} />
                                            </TableCell>
                                            <TableCell>
                                                {approvalRequest.amount !== null &&
                                                approvalRequest.amount !== undefined
                                                    ? `${approvalRequest.currency_code ?? ''} ${approvalRequest.amount.toFixed(2)}`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {approvalRequest.risk_level ? (
                                                    <Badge
                                                        variant={riskVariant(approvalRequest.risk_level)}
                                                        className="capitalize"
                                                    >
                                                        {approvalRequest.risk_level}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {approvalRequest.requested_by ?? '-'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDateTime(approvalRequest.requested_at)}
                                            </TableCell>
                                            <TableCell>
                                                {decisionBy ? (
                                                    <>
                                                        <p>{decisionBy}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatDateTime(decisionAt)}
                                                        </p>
                                                        {approvalRequest.rejection_reason && (
                                                            <p className="max-w-[220px] truncate text-xs text-muted-foreground">
                                                                {approvalRequest.rejection_reason}
                                                            </p>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {canManage && approvalRequest.status === 'pending' ? (
                                                    <div className="inline-flex items-center gap-1">
                                                        <Button
                                                            size="sm"
                                                            type="button"
                                                            onClick={() => handleApprove(approvalRequest.id)}
                                                            disabled={!approvalRequest.can_approve}
                                                        >
                                                            Approve
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            type="button"
                                                            onClick={() => {
                                                                rejectForm.reset();
                                                                rejectForm.clearErrors();
                                                                setRejectingRequestId(
                                                                    approvalRequest.id,
                                                                );
                                                            }}
                                                            disabled={!approvalRequest.can_approve}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        {approvalRequest.status === 'pending' &&
                                                        !approvalRequest.can_approve
                                                            ? 'No authority'
                                                            : 'Closed'}
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </DataTableShell>
                }
                pagination={<PaginationBar links={approvalRequests.links} />}
            >
                <FilterToolbar
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/approvals', {
                            preserveState: true,
                            preserveScroll: true,
                        });
                    }}
                >
                    <FilterToolbarGrid>
                        <FilterField label="Status" htmlFor="status">
                            <select
                                id="status"
                                className="h-10 w-full rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
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
                        </FilterField>

                        <FilterField label="Module" htmlFor="module">
                            <select
                                id="module"
                                className="h-10 w-full rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
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
                                <option value="projects">Projects</option>
                            </select>
                        </FilterField>

                        <FilterField label="Start date" htmlFor="start_date">
                            <Input
                                id="start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) =>
                                    form.setData('start_date', event.target.value)
                                }
                            />
                        </FilterField>

                        <FilterField label="End date" htmlFor="end_date">
                            <Input
                                id="end_date"
                                type="date"
                                value={form.data.end_date}
                                onChange={(event) =>
                                    form.setData('end_date', event.target.value)
                                }
                            />
                        </FilterField>
                    </FilterToolbarGrid>

                    <FilterToolbarActions className="mt-4">
                        <Button type="submit" disabled={form.processing}>
                            Apply filters
                        </Button>
                        <Button variant="outline" type="button" asChild>
                            <Link href="/company/approvals">Reset</Link>
                        </Button>
                    </FilterToolbarActions>
                </FilterToolbar>
            </WorkspaceShell>

            <ReasonDialog
                open={rejectingRequest !== null}
                onOpenChange={handleRejectOpenChange}
                title="Reject approval request?"
                description={
                    rejectingRequest
                        ? `This will mark ${rejectingRequest.source_number ?? 'the selected request'} as rejected and record the decision in the approval history.`
                        : 'This will mark the selected request as rejected and record the decision in the approval history.'
                }
                confirmLabel="Reject request"
                processingLabel="Rejecting..."
                cancelLabel="Keep pending"
                processing={rejectForm.processing}
                onConfirm={handleReject}
                reason={rejectForm.data.reason}
                onReasonChange={(value) => rejectForm.setData('reason', value)}
                reasonLabel="Rejection reason"
                reasonPlaceholder="Optional context for the requester or downstream reviewers."
                reasonHelperText="A reason is optional, but it helps explain why the request was rejected."
                reasonError={rejectForm.errors.reason}
                errors={{
                    approval: rejectErrors.approval,
                    reason: rejectForm.errors.reason,
                }}
            />
        </AppLayout>
    );
}
