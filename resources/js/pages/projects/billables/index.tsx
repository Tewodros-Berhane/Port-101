import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ReasonDialog } from '@/components/feedback/reason-dialog';
import { BackLinkAction } from '@/components/navigation/back-link-action';

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
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type FilterOption = {
    id: string;
    name: string;
};

type ProjectFilterOption = FilterOption & {
    project_code: string;
    customer_name?: string | null;
};

type BillableRow = {
    id: string;
    project_id: string;
    project_code?: string | null;
    project_name?: string | null;
    customer_name?: string | null;
    billable_type: string;
    description?: string | null;
    status: string;
    approval_status: string;
    approved_by_name?: string | null;
    approved_at?: string | null;
    rejected_by_name?: string | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
    cancelled_by_name?: string | null;
    cancelled_at?: string | null;
    cancellation_reason?: string | null;
    quantity: number;
    unit_price: number;
    amount: number;
    currency_code?: string | null;
    invoice_id?: string | null;
    invoice_number?: string | null;
    updated_at?: string | null;
    requires_approval: boolean;
    can_open_project: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
    can_create_invoice: boolean;
    can_open_invoice: boolean;
};

type Props = {
    filters: {
        project_id: string;
        customer_id: string;
        status: string;
        approval_status: string;
        billable_type: string;
    };
    statuses: string[];
    approvalStatuses: string[];
    billableTypes: string[];
    projectsFilterOptions: ProjectFilterOption[];
    customersFilterOptions: FilterOption[];
    summary: {
        ready_to_invoice_count: number;
        pending_approval_count: number;
        invoiced_count: number;
        uninvoiced_amount: number;
    };
    billables: {
        data: BillableRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    abilities: {
        can_view_projects_workspace: boolean;
        can_create_invoice_drafts: boolean;
        invoiceGroupingOptions: string[];
    };
};

type BillableDecisionDialogState = {
    action: 'reject' | 'cancel';
    id: string;
    projectLabel: string;
    description: string;
    amount: number;
    currencyCode?: string | null;
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function ProjectBillablesIndex({
    filters,
    statuses,
    approvalStatuses,
    billableTypes,
    projectsFilterOptions,
    customersFilterOptions,
    summary,
    billables,
    abilities,
}: Props) {
    const [selectedBillableIds, setSelectedBillableIds] = useState<string[]>([]);
    const [groupBy, setGroupBy] = useState(
        abilities.invoiceGroupingOptions[0] ?? 'project',
    );
    const [decisionDialog, setDecisionDialog] =
        useState<BillableDecisionDialogState | null>(null);
    const form = useForm({
        project_id: filters.project_id,
        customer_id: filters.customer_id,
        status: filters.status,
        approval_status: filters.approval_status,
        billable_type: filters.billable_type,
    });
    const decisionForm = useForm({
        reason: '',
    });

    const openDecisionDialog = (
        action: 'reject' | 'cancel',
        billable: BillableRow,
        currentReason?: string | null,
    ) => {
        decisionForm.setData('reason', currentReason ?? '');
        decisionForm.clearErrors();
        setDecisionDialog({
            action,
            id: billable.id,
            projectLabel: billable.project_code
                ? billable.project_name
                    ? `${billable.project_code} - ${billable.project_name}`
                    : billable.project_code
                : billable.project_name ?? 'Unknown project',
            description: billable.description ?? 'No description',
            amount: billable.amount,
            currencyCode: billable.currency_code,
        });
    };

    const closeDecisionDialog = (open: boolean) => {
        if (decisionForm.processing) {
            return;
        }

        if (!open) {
            decisionForm.reset();
            decisionForm.clearErrors();
            setDecisionDialog(null);
        }
    };

    const submitDecision = () => {
        if (!decisionDialog) {
            return;
        }

        decisionForm.post(
            `/company/projects/billables/${decisionDialog.id}/${decisionDialog.action}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    decisionForm.reset();
                    decisionForm.clearErrors();
                    setDecisionDialog(null);
                },
            },
        );
    };

    const selectableBillableIds = useMemo(
        () =>
            billables.data
                .filter((billable) => billable.can_create_invoice)
                .map((billable) => billable.id),
        [billables.data],
    );

    const activeSelectedBillableIds = useMemo(
        () =>
            selectedBillableIds.filter((id) =>
                selectableBillableIds.includes(id),
            ),
        [selectedBillableIds, selectableBillableIds],
    );

    const allSelectableRowsChecked =
        selectableBillableIds.length > 0 &&
        selectableBillableIds.every((id) => activeSelectedBillableIds.includes(id));

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, { title: 'Billing Queue', href: '/company/projects/billables' },)}
        >
            <Head title="Projects Billing Queue" />

            <WorkspaceShell
                header={
                    <PageHeader
                        title="Projects billing queue"
                        description="Review generated project billables before approval and create accounting invoice drafts from eligible items."
                        actions={
                            <>
                                <BackLinkAction href="/company/projects" label="Back to projects" variant="outline" />
                                {abilities.can_view_projects_workspace && (
                                    <Button variant="outline" asChild>
                                        <Link href="/company/projects/workspace">
                                            Workspace
                                        </Link>
                                    </Button>
                                )}
                                {abilities.can_create_invoice_drafts && (
                                    <>
                                        <select
                                            className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            value={groupBy}
                                            onChange={(event) =>
                                                setGroupBy(event.target.value)
                                            }
                                        >
                                            {abilities.invoiceGroupingOptions.map(
                                                (option) => (
                                                    <option
                                                        key={option}
                                                        value={option}
                                                    >
                                                        Group by {formatLabel(option)}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <Button
                                            type="button"
                                            disabled={activeSelectedBillableIds.length === 0}
                                            onClick={() =>
                                                router.post(
                                                    '/company/projects/billables/invoice-drafts',
                                                    {
                                                        billable_ids:
                                                            activeSelectedBillableIds,
                                                        group_by: groupBy,
                                                    },
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            Create draft invoice
                                            {activeSelectedBillableIds.length > 0 &&
                                                ` (${activeSelectedBillableIds.length})`}
                                        </Button>
                                    </>
                                )}
                            </>
                        }
                        meta={
                            <>
                                <span>{summary.ready_to_invoice_count} ready to invoice</span>
                                <span className="h-1 w-1 rounded-full bg-[color:var(--text-muted)]" />
                                <span>{billables.data.length} rows on this page</span>
                            </>
                        }
                    />
                }
                kpis={
                    <KpiStrip>
                        <MetricCard
                            label="Ready to invoice"
                            value={String(summary.ready_to_invoice_count)}
                            tone="success"
                        />
                        <MetricCard
                            label="Pending approval"
                            value={String(summary.pending_approval_count)}
                            tone="warning"
                        />
                        <MetricCard
                            label="Invoiced"
                            value={String(summary.invoiced_count)}
                            tone="info"
                        />
                        <MetricCard
                            label="Uninvoiced amount"
                            value={summary.uninvoiced_amount.toFixed(2)}
                        />
                    </KpiStrip>
                }
                table={
                    <DataTableShell>
                        <Table container={false} className="min-w-[1380px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        <input
                                            type="checkbox"
                                            className="size-4 rounded border-input"
                                            checked={allSelectableRowsChecked}
                                            onChange={(event) =>
                                                setSelectedBillableIds(
                                                    event.target.checked
                                                        ? selectableBillableIds
                                                        : [],
                                                )
                                            }
                                            aria-label="Select all invoice-eligible billables"
                                        />
                                    </TableHead>
                                    <TableHead>Project</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">Qty</TableHead>
                                    <TableHead className="text-right">Unit price</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Approval</TableHead>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead>Updated</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {billables.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={13}
                                            className="py-12 text-center text-sm text-muted-foreground"
                                        >
                                            No billables match the current filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {billables.data.map((billable) => (
                                    <TableRow key={billable.id}>
                                        <TableCell>
                                            {billable.can_create_invoice ? (
                                                <input
                                                    type="checkbox"
                                                    className="size-4 rounded border-input"
                                                    checked={activeSelectedBillableIds.includes(
                                                        billable.id,
                                                    )}
                                                    onChange={(event) =>
                                                        setSelectedBillableIds((current) =>
                                                            event.target.checked
                                                                ? current.includes(billable.id)
                                                                    ? current
                                                                    : [...current, billable.id]
                                                                : current.filter(
                                                                      (id) => id !== billable.id,
                                                                  ),
                                                        )
                                                    }
                                                    aria-label={`Select billable ${billable.id}`}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <p className="font-medium">
                                                {billable.project_code ?? '-'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {billable.project_name ?? '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {billable.customer_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <BadgeLike>{formatLabel(billable.billable_type)}</BadgeLike>
                                        </TableCell>
                                        <TableCell>
                                            <div className="max-w-[320px]">
                                                <p className="font-medium">
                                                    {billable.description ?? 'No description'}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {billable.quantity.toFixed(2)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {billable.unit_price.toFixed(2)}
                                            <p className="text-xs text-muted-foreground">
                                                {billable.currency_code ?? '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {billable.amount.toFixed(2)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={billable.status} />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={billable.approval_status} />
                                            <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                                                {billable.approved_at && (
                                                    <p>
                                                        Approved by {billable.approved_by_name ?? 'Unknown'} on{' '}
                                                        {formatDateTime(billable.approved_at)}
                                                    </p>
                                                )}
                                                {billable.rejected_at && (
                                                    <p>
                                                        Rejected by {billable.rejected_by_name ?? 'Unknown'} on{' '}
                                                        {formatDateTime(billable.rejected_at)}
                                                    </p>
                                                )}
                                                {billable.rejection_reason && (
                                                    <p className="max-w-[220px] truncate">
                                                        {billable.rejection_reason}
                                                    </p>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {billable.invoice_number ? (
                                                billable.can_open_invoice ? (
                                                    <Link
                                                        href={`/company/accounting/invoices/${billable.invoice_id}/edit`}
                                                        className="font-medium text-primary"
                                                    >
                                                        {billable.invoice_number}
                                                    </Link>
                                                ) : (
                                                    billable.invoice_number
                                                )
                                            ) : billable.status === 'invoiced' ? (
                                                'Invoiced'
                                            ) : (
                                                'Not invoiced'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateTime(billable.updated_at)}
                                            {billable.cancelled_at && (
                                                <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                                                    <p>
                                                        Cancelled by {billable.cancelled_by_name ?? 'Unknown'} on{' '}
                                                        {formatDateTime(billable.cancelled_at)}
                                                    </p>
                                                    {billable.cancellation_reason && (
                                                        <p className="max-w-[220px] truncate">
                                                            {billable.cancellation_reason}
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="inline-flex flex-wrap items-center justify-end gap-1">
                                                {billable.can_approve && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/projects/billables/${billable.id}/approve`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </Button>
                                                )}
                                                {billable.can_reject && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openDecisionDialog(
                                                                'reject',
                                                                billable,
                                                                billable.rejection_reason,
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </Button>
                                                )}
                                                {billable.can_cancel && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openDecisionDialog(
                                                                'cancel',
                                                                billable,
                                                                billable.cancellation_reason,
                                                            )
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                )}
                                                {billable.can_open_project ? (
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/company/projects/${billable.project_id}`}>
                                                            Open project
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        View only
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </DataTableShell>
                }
                pagination={<PaginationBar links={billables.links} />}
            >
                <FilterToolbar
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/company/projects/billables', form.data, {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <FilterToolbarGrid className="xl:grid-cols-5">
                        <FilterField label="Project" htmlFor="project_id">
                            <select
                                id="project_id"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.project_id}
                                onChange={(event) =>
                                    form.setData('project_id', event.target.value)
                                }
                            >
                                <option value="">All projects</option>
                                {projectsFilterOptions.map((project) => (
                                    <option key={project.id} value={project.id}>
                                        {project.project_code} - {project.name}
                                    </option>
                                ))}
                            </select>
                        </FilterField>

                        <FilterField label="Customer" htmlFor="customer_id">
                            <select
                                id="customer_id"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.customer_id}
                                onChange={(event) =>
                                    form.setData('customer_id', event.target.value)
                                }
                            >
                                <option value="">All customers</option>
                                {customersFilterOptions.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                        <FilterField label="Status" htmlFor="status">
                            <select
                                id="status"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                <option value="">All statuses</option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {formatLabel(status)}
                                    </option>
                                ))}
                            </select>
                        </FilterField>

                        <FilterField label="Approval" htmlFor="approval_status">
                            <select
                                id="approval_status"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.approval_status}
                                onChange={(event) =>
                                    form.setData('approval_status', event.target.value)
                                }
                            >
                                <option value="">All approval states</option>
                                {approvalStatuses.map((status) => (
                                    <option key={status} value={status}>
                                        {formatLabel(status)}
                                    </option>
                                ))}
                            </select>
                        </FilterField>

                        <FilterField label="Billable type" htmlFor="billable_type">
                            <select
                                id="billable_type"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={form.data.billable_type}
                                onChange={(event) =>
                                    form.setData('billable_type', event.target.value)
                                }
                            >
                                <option value="">All billable types</option>
                                {billableTypes.map((billableType) => (
                                    <option key={billableType} value={billableType}>
                                        {formatLabel(billableType)}
                                    </option>
                                ))}
                            </select>
                        </FilterField>
                    </FilterToolbarGrid>

                    <FilterToolbarActions className="mt-4">
                        <Button type="submit">Apply filters</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const resetFilters = {
                                    project_id: '',
                                    customer_id: '',
                                    status: '',
                                    approval_status: '',
                                    billable_type: '',
                                };

                                form.setData(resetFilters);
                                router.get('/company/projects/billables', resetFilters, {
                                    preserveState: true,
                                    replace: true,
                                });
                            }}
                        >
                            Reset
                        </Button>
                    </FilterToolbarActions>
                </FilterToolbar>

                {abilities.can_create_invoice_drafts && (
                    <Card className="gap-0 py-0">
                        <CardContent className="px-5 py-4 text-sm text-muted-foreground">
                            Select ready billables from the table to create draft customer invoices in Accounting. Pending, rejected, cancelled, or already invoiced rows are excluded.
                        </CardContent>
                    </Card>
                )}
            </WorkspaceShell>

            <ReasonDialog
                open={decisionDialog !== null}
                onOpenChange={closeDecisionDialog}
                title={
                    decisionDialog
                        ? decisionDialog.action === 'reject'
                            ? 'Reject project billable?'
                            : 'Cancel project billable?'
                        : 'Update billable?'
                }
                description={
                    decisionDialog?.action === 'reject'
                        ? 'This marks the billable as rejected and keeps it out of the invoice queue until it is corrected or recreated.'
                        : 'This cancels the billable and removes it from further billing activity.'
                }
                confirmLabel={
                    decisionDialog?.action === 'reject'
                        ? 'Reject billable'
                        : 'Cancel billable'
                }
                processingLabel={
                    decisionDialog?.action === 'reject'
                        ? 'Rejecting...'
                        : 'Cancelling...'
                }
                cancelLabel="Keep billable"
                processing={decisionForm.processing}
                onConfirm={submitDecision}
                reason={decisionForm.data.reason}
                onReasonChange={(value) => decisionForm.setData('reason', value)}
                reasonLabel={
                    decisionDialog?.action === 'reject'
                        ? 'Rejection note'
                        : 'Cancellation note'
                }
                reasonPlaceholder={
                    decisionDialog?.action === 'reject'
                        ? 'Optional note for why this billable is being rejected.'
                        : 'Optional note for why this billable is being cancelled.'
                }
                reasonHelperText={
                    decisionDialog
                        ? `${decisionDialog.projectLabel} | ${decisionDialog.amount.toFixed(2)} ${decisionDialog.currencyCode ?? ''} | ${decisionDialog.description}`
                        : undefined
                }
                reasonError={decisionForm.errors.reason}
                errors={decisionForm.errors}
            />
        </AppLayout>
    );
}

function BadgeLike({ children }: { children: string }) {
    return (
        <span className="inline-flex rounded-full border border-[color:var(--border-subtle)] bg-muted/35 px-2.5 py-1 text-xs font-medium text-[color:var(--text-secondary)]">
            {children}
        </span>
    );
}
