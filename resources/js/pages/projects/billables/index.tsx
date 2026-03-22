import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

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
    invoice_number?: string | null;
    updated_at?: string | null;
    requires_approval: boolean;
    can_open_project: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_cancel: boolean;
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
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

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
    const form = useForm({
        project_id: filters.project_id,
        customer_id: filters.customer_id,
        status: filters.status,
        approval_status: filters.approval_status,
        billable_type: filters.billable_type,
    });

    const withReason = (
        label: string,
        callback: (reason: string) => void,
        currentReason?: string | null,
    ) => {
        const reason = window.prompt(
            `${label} reason (optional)`,
            currentReason ?? '',
        );

        if (reason === null) {
            return;
        }

        callback(reason);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Projects', href: '/company/projects' },
                { title: 'Billing Queue', href: '/company/projects/billables' },
            ]}
        >
            <Head title="Projects Billing Queue" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Projects billing queue
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Review generated project billables before approval
                            and invoice handoff.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/company/projects">Dashboard</Link>
                        </Button>
                        {abilities.can_view_projects_workspace && (
                            <Button variant="outline" asChild>
                                <Link href="/company/projects/workspace">
                                    Workspace
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/projects/billables', {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="project_id">Project</Label>
                        <select
                            id="project_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
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
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="customer_id">Customer</Label>
                        <select
                            id="customer_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
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
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
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
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="approval_status">Approval</Label>
                        <select
                            id="approval_status"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.approval_status}
                            onChange={(event) =>
                                form.setData(
                                    'approval_status',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">All approval states</option>
                            {approvalStatuses.map((status) => (
                                <option key={status} value={status}>
                                    {formatLabel(status)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="billable_type">Billable type</Label>
                        <select
                            id="billable_type"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.billable_type}
                            onChange={(event) =>
                                form.setData(
                                    'billable_type',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">All billable types</option>
                            {billableTypes.map((billableType) => (
                                <option
                                    key={billableType}
                                    value={billableType}
                                >
                                    {formatLabel(billableType)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-5">
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
                                form.get('/company/projects/billables', {
                                    data: resetFilters,
                                    preserveState: true,
                                    replace: true,
                                });
                            }}
                        >
                            Reset
                        </Button>
                    </div>
                </form>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Ready to invoice"
                        value={String(summary.ready_to_invoice_count)}
                    />
                    <MetricCard
                        label="Pending approval"
                        value={String(summary.pending_approval_count)}
                    />
                    <MetricCard
                        label="Invoiced"
                        value={String(summary.invoiced_count)}
                    />
                    <MetricCard
                        label="Uninvoiced amount"
                        value={summary.uninvoiced_amount.toFixed(2)}
                    />
                </section>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[1320px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Project
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Customer
                                </th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">
                                    Description
                                </th>
                                <th className="px-4 py-3 font-medium">Qty</th>
                                <th className="px-4 py-3 font-medium">
                                    Unit price
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Amount
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Approval
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Invoice
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Updated
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {billables.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={12}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No billables match the current filters.
                                    </td>
                                </tr>
                            )}
                            {billables.data.map((billable) => (
                                <tr key={billable.id}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {billable.project_code ?? '-'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {billable.project_name ?? '-'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {billable.customer_name ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatLabel(billable.billable_type)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="max-w-[320px]">
                                            <p className="font-medium">
                                                {billable.description ??
                                                    'No description'}
                                            </p>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {billable.quantity.toFixed(2)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {billable.unit_price.toFixed(2)}
                                        <p className="text-xs text-muted-foreground">
                                            {billable.currency_code ?? '-'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="font-medium">
                                            {billable.amount.toFixed(2)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatLabel(billable.status)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatLabel(
                                            billable.approval_status,
                                        )}
                                        <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                                            {billable.approved_at && (
                                                <p>
                                                    Approved by{' '}
                                                    {billable.approved_by_name ??
                                                        'Unknown'}{' '}
                                                    on{' '}
                                                    {new Date(
                                                        billable.approved_at,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                            {billable.rejected_at && (
                                                <p>
                                                    Rejected by{' '}
                                                    {billable.rejected_by_name ??
                                                        'Unknown'}{' '}
                                                    on{' '}
                                                    {new Date(
                                                        billable.rejected_at,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                            {billable.rejection_reason && (
                                                <p className="max-w-[220px] truncate">
                                                    {billable.rejection_reason}
                                                </p>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {billable.invoice_number ??
                                            (billable.status === 'invoiced'
                                                ? 'Invoiced'
                                                : 'Not invoiced')}
                                    </td>
                                    <td className="px-4 py-3">
                                        {billable.updated_at
                                            ? new Date(
                                                  billable.updated_at,
                                              ).toLocaleString()
                                            : '-'}
                                        {billable.cancelled_at && (
                                            <div className="mt-1 space-y-1 text-xs text-muted-foreground">
                                                <p>
                                                    Cancelled by{' '}
                                                    {billable.cancelled_by_name ??
                                                        'Unknown'}{' '}
                                                    on{' '}
                                                    {new Date(
                                                        billable.cancelled_at,
                                                    ).toLocaleString()}
                                                </p>
                                                {billable.cancellation_reason && (
                                                    <p className="max-w-[220px] truncate">
                                                        {
                                                            billable.cancellation_reason
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="inline-flex flex-wrap items-center justify-end gap-3">
                                            {billable.can_approve && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() =>
                                                        router.post(
                                                            `/company/projects/billables/${billable.id}/approve`,
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Approve
                                                </button>
                                            )}
                                            {billable.can_reject && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() =>
                                                        withReason(
                                                            'Reject billable',
                                                            (reason) =>
                                                                router.post(
                                                                    `/company/projects/billables/${billable.id}/reject`,
                                                                    {
                                                                        reason,
                                                                    },
                                                                    {
                                                                        preserveScroll:
                                                                            true,
                                                                    },
                                                                ),
                                                            billable.rejection_reason,
                                                        )
                                                    }
                                                >
                                                    Reject
                                                </button>
                                            )}
                                            {billable.can_cancel && (
                                                <button
                                                    type="button"
                                                    className="font-medium text-primary"
                                                    onClick={() =>
                                                        withReason(
                                                            'Cancel billable',
                                                            (reason) =>
                                                                router.post(
                                                                    `/company/projects/billables/${billable.id}/cancel`,
                                                                    {
                                                                        reason,
                                                                    },
                                                                    {
                                                                        preserveScroll:
                                                                            true,
                                                                    },
                                                                ),
                                                            billable.cancellation_reason,
                                                        )
                                                    }
                                                >
                                                    Cancel
                                                </button>
                                            )}
                                            {billable.can_open_project ? (
                                                <Link
                                                    href={`/company/projects/${billable.project_id}`}
                                                    className="font-medium text-primary"
                                                >
                                                    Open project
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    View only
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {billables.links.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {billables.links.map((link) => (
                            <Link
                                key={link.label}
                                href={link.url ?? '#'}
                                className={`rounded-md border px-3 py-1 text-sm ${
                                    link.active
                                        ? 'border-primary text-primary'
                                        : 'text-muted-foreground'
                                } ${
                                    !link.url
                                        ? 'pointer-events-none opacity-50'
                                        : ''
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
