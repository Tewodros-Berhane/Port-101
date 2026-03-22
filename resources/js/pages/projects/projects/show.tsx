import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type ProjectMember = {
    id: string;
    user_id?: string | null;
    name?: string | null;
    email?: string | null;
    project_role: string;
    allocation_percent?: number | null;
};

type ProjectTask = {
    id: string;
    task_number: string;
    title: string;
    status: string;
    priority: string;
    stage_name?: string | null;
    assignee_name?: string | null;
    due_date?: string | null;
    estimated_hours?: number | null;
    actual_hours: number;
    can_edit: boolean;
};

type ProjectTimesheet = {
    id: string;
    user_name?: string | null;
    task_number?: string | null;
    task_title?: string | null;
    work_date?: string | null;
    hours: number;
    is_billable: boolean;
    cost_amount: number;
    billable_amount: number;
    approval_status: string;
    invoice_status: string;
    rejection_reason?: string | null;
    can_edit: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
};

type ProjectMilestone = {
    id: string;
    name: string;
    sequence: number;
    status: string;
    due_date?: string | null;
    completed_at?: string | null;
    amount: number;
    invoice_status: string;
    approved_by_name?: string | null;
    approved_at?: string | null;
    can_edit: boolean;
};

type ProjectBillable = {
    id: string;
    billable_type: string;
    description?: string | null;
    status: string;
    approval_status: string;
    quantity: number;
    unit_price: number;
    amount: number;
    currency_code?: string | null;
    invoice_id?: string | null;
    invoice_number?: string | null;
    invoice_status?: string | null;
    updated_at?: string | null;
    can_create_invoice: boolean;
    can_open_invoice: boolean;
};

type LinkedInvoice = {
    id: string;
    invoice_number: string;
    status: string;
    invoice_date?: string | null;
    due_date?: string | null;
    grand_total: number;
    balance_due: number;
    can_open: boolean;
};

type Props = {
    project: {
        id: string;
        project_code: string;
        name: string;
        description?: string | null;
        status: string;
        billing_type: string;
        health_status: string;
        customer_name?: string | null;
        sales_order_number?: string | null;
        currency_code?: string | null;
        project_manager_name?: string | null;
        project_manager_email?: string | null;
        start_date?: string | null;
        target_end_date?: string | null;
        completed_at?: string | null;
        budget_amount?: number | null;
        budget_hours?: number | null;
        actual_cost_amount: number;
        actual_billable_amount: number;
        progress_percent: number;
        members: ProjectMember[];
        tasks: ProjectTask[];
        timesheets: ProjectTimesheet[];
        milestones: ProjectMilestone[];
        billables: ProjectBillable[];
        linked_invoices: LinkedInvoice[];
    };
    summary: {
        task_total: number;
        task_completed: number;
        task_overdue: number;
        team_members: number;
        timesheets_logged: number;
        timesheets_pending_approval: number;
        milestones_total: number;
        milestones_ready_review: number;
        billables_logged: number;
        billables_ready_to_invoice: number;
        billables_ready_to_invoice_amount: number;
        billables_pending_approval: number;
        billables_pending_approval_amount: number;
        billables_invoiced: number;
        billables_invoiced_amount: number;
    };
    profitability: {
        budget_hours?: number | null;
        logged_hours: number;
        approved_hours: number;
        remaining_hours?: number | null;
        utilization_percent?: number | null;
        budget_amount?: number | null;
        actual_cost_amount: number;
        budget_consumed_percent?: number | null;
        billable_pipeline_amount: number;
        ready_to_invoice_amount: number;
        pending_approval_amount: number;
        invoiced_amount: number;
        gross_margin_amount: number;
        gross_margin_percent?: number | null;
        realization_percent?: number | null;
    };
    abilities: {
        can_edit_project: boolean;
        can_create_task: boolean;
        can_create_timesheet: boolean;
        can_create_milestone: boolean;
        can_view_billables: boolean;
        can_create_invoice_drafts: boolean;
        invoice_grouping_options: string[];
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectShow({
    project,
    summary,
    profitability,
    abilities,
}: Props) {
    const [selectedBillableIds, setSelectedBillableIds] = useState<string[]>([]);
    const [groupBy, setGroupBy] = useState(
        abilities.invoice_grouping_options[0] ?? 'project',
    );

    const handleRejectTimesheet = (timesheetId: string, rejectionReason?: string | null) => {
        const reason =
            window.prompt('Optional rejection reason', rejectionReason ?? '') ?? '';

        router.post(
            `/company/projects/timesheets/${timesheetId}/reject`,
            { reason },
            { preserveScroll: true },
        );
    };

    const selectableBillableIds = useMemo(
        () =>
            project.billables
                .filter((billable) => billable.can_create_invoice)
                .map((billable) => billable.id),
        [project.billables],
    );

    useEffect(() => {
        setSelectedBillableIds((current) =>
            current.filter((id) => selectableBillableIds.includes(id)),
        );
    }, [selectableBillableIds]);

    const allSelectableBillablesChecked =
        selectableBillableIds.length > 0 &&
        selectableBillableIds.every((id) => selectedBillableIds.includes(id));

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Projects', href: '/company/projects' },
                { title: 'Workspace', href: '/company/projects/workspace' },
                {
                    title: project.project_code,
                    href: `/company/projects/${project.id}`,
                },
            ]}
        >
            <Head title={project.project_code} />

            <div className="space-y-6">
                <section className="rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-chart-1/10 via-transparent to-chart-3/10 p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-medium tracking-[0.24em] text-muted-foreground uppercase">
                                {project.project_code}
                            </p>
                            <h1 className="mt-2 text-2xl font-semibold">
                                {project.name}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                                {project.description ||
                                    'No project summary added yet.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/company/projects/workspace">
                                    Back to workspace
                                </Link>
                            </Button>
                            {abilities.can_view_billables && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/company/projects/billables?project_id=${project.id}`}
                                    >
                                        Billing queue
                                    </Link>
                                </Button>
                            )}
                            {abilities.can_create_invoice_drafts && (
                                <>
                                    <select
                                        className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={groupBy}
                                        onChange={(event) =>
                                            setGroupBy(event.target.value)
                                        }
                                    >
                                        {abilities.invoice_grouping_options.map(
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
                                        disabled={
                                            selectedBillableIds.length === 0
                                        }
                                        onClick={() =>
                                            router.post(
                                                '/company/projects/billables/invoice-drafts',
                                                {
                                                    billable_ids:
                                                        selectedBillableIds,
                                                    group_by: groupBy,
                                                },
                                                {
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                    >
                                        Create draft invoice
                                        {selectedBillableIds.length > 0 &&
                                            ` (${selectedBillableIds.length})`}
                                    </Button>
                                </>
                            )}
                            {abilities.can_create_task && (
                                <Button asChild>
                                    <Link
                                        href={`/company/projects/${project.id}/tasks/create`}
                                    >
                                        New task
                                    </Link>
                                </Button>
                            )}
                            {abilities.can_edit_project && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/company/projects/${project.id}/edit`}
                                    >
                                        Edit project
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <MetricCard label="Status" value={formatLabel(project.status)} />
                    <MetricCard
                        label="Billing type"
                        value={formatLabel(project.billing_type)}
                    />
                    <MetricCard
                        label="Health"
                        value={formatLabel(project.health_status)}
                    />
                    <MetricCard
                        label="Progress"
                        value={`${project.progress_percent.toFixed(1)}%`}
                    />
                    <MetricCard
                        label="Task completion"
                        value={`${summary.task_completed}/${summary.task_total}`}
                    />
                    <MetricCard
                        label="Ready to invoice"
                        value={`${summary.billables_ready_to_invoice} / ${summary.billables_ready_to_invoice_amount.toFixed(2)}`}
                    />
                    <MetricCard
                        label="Overdue tasks"
                        value={String(summary.task_overdue)}
                    />
                </section>

                <section className="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
                    <div className="rounded-xl border p-4">
                        <h2 className="text-sm font-semibold">Project overview</h2>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <DetailRow label="Customer" value={project.customer_name} />
                            <DetailRow
                                label="Sales order"
                                value={project.sales_order_number}
                            />
                            <DetailRow
                                label="Currency"
                                value={project.currency_code}
                            />
                            <DetailRow
                                label="Project manager"
                                value={project.project_manager_name}
                            />
                            <DetailRow label="Manager email" value={project.project_manager_email} />
                            <DetailRow label="Start date" value={project.start_date} />
                            <DetailRow
                                label="Target end"
                                value={project.target_end_date}
                            />
                            <DetailRow
                                label="Completed at"
                                value={project.completed_at}
                            />
                            <DetailRow
                                label="Budget amount"
                                value={
                                    project.budget_amount !== null &&
                                    project.budget_amount !== undefined
                                        ? project.budget_amount.toFixed(2)
                                        : '-'
                                }
                            />
                            <DetailRow
                                label="Budget hours"
                                value={
                                    project.budget_hours !== null &&
                                    project.budget_hours !== undefined
                                        ? project.budget_hours.toFixed(2)
                                        : '-'
                                }
                            />
                            <DetailRow
                                label="Actual cost"
                                value={project.actual_cost_amount.toFixed(2)}
                            />
                            <DetailRow
                                label="Actual billable"
                                value={project.actual_billable_amount.toFixed(2)}
                            />
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <h2 className="text-sm font-semibold">Execution summary</h2>
                        <div className="mt-4 space-y-2 text-sm">
                            <SummaryRow
                                label="Team members"
                                value={summary.team_members}
                            />
                            <SummaryRow
                                label="Timesheets logged"
                                value={summary.timesheets_logged}
                            />
                            <SummaryRow
                                label="Pending approvals"
                                value={summary.timesheets_pending_approval}
                            />
                            <SummaryRow
                                label="Milestones"
                                value={summary.milestones_total}
                            />
                            <SummaryRow
                                label="Review ready"
                                value={summary.milestones_ready_review}
                            />
                            <SummaryRow
                                label="Billables logged"
                                value={summary.billables_logged}
                            />
                            <SummaryRow
                                label="Ready to invoice"
                                value={summary.billables_ready_to_invoice}
                            />
                            <SummaryRow
                                label="Pending billables"
                                value={summary.billables_pending_approval}
                            />
                            <SummaryRow
                                label="Invoiced billables"
                                value={summary.billables_invoiced}
                            />
                            <SummaryRow
                                label="Open tasks"
                                value={summary.task_total - summary.task_completed}
                            />
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Billing summary
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Invoice-ready, pending approval, and posted
                                    billing signals for this project.
                                </p>
                            </div>
                            {abilities.can_view_billables && (
                                <Button variant="ghost" asChild>
                                    <Link
                                        href={`/company/projects/billables?project_id=${project.id}`}
                                    >
                                        Open billing queue
                                    </Link>
                                </Button>
                            )}
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <BillingMetricCard
                                label="Ready to invoice"
                                count={summary.billables_ready_to_invoice}
                                amount={summary.billables_ready_to_invoice_amount}
                                tone="emerald"
                            />
                            <BillingMetricCard
                                label="Pending approval"
                                count={summary.billables_pending_approval}
                                amount={summary.billables_pending_approval_amount}
                                tone="amber"
                            />
                            <BillingMetricCard
                                label="Invoiced"
                                count={summary.billables_invoiced}
                                amount={summary.billables_invoiced_amount}
                                tone="blue"
                            />
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Linked invoices
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Draft and posted accounting invoices created
                                    from this project.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            {project.linked_invoices.length === 0 && (
                                <div className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                    No invoices linked to this project yet.
                                </div>
                            )}
                            {project.linked_invoices.map((invoice) => (
                                <div
                                    key={invoice.id}
                                    className="rounded-xl border px-3 py-3"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            {invoice.can_open ? (
                                                <Link
                                                    href={`/company/accounting/invoices/${invoice.id}/edit`}
                                                    className="text-sm font-medium text-primary"
                                                >
                                                    {invoice.invoice_number}
                                                </Link>
                                            ) : (
                                                <p className="text-sm font-medium">
                                                    {invoice.invoice_number}
                                                </p>
                                            )}
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatLabel(invoice.status)} •
                                                Invoice {invoice.invoice_date ?? '-'}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold">
                                            {invoice.grand_total.toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                        <span>Due {invoice.due_date ?? '-'}</span>
                                        <span>
                                            Balance {invoice.balance_due.toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Profitability
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Budget, effort, billing pipeline, and margin
                                signals for this project.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <BillingMetricCard
                            label="Logged hours"
                            count={Number(profitability.logged_hours.toFixed(2))}
                            amount={profitability.budget_hours ?? 0}
                            tone="blue"
                            countLabel="logged"
                            amountLabel={
                                profitability.budget_hours !== null &&
                                profitability.budget_hours !== undefined
                                    ? 'budget'
                                    : 'no budget'
                            }
                        />
                        <BillingMetricCard
                            label="Billable pipeline"
                            count={Number(
                                profitability.ready_to_invoice_amount.toFixed(2),
                            )}
                            amount={profitability.billable_pipeline_amount}
                            tone="emerald"
                            countLabel="ready"
                            amountLabel="pipeline"
                        />
                        <BillingMetricCard
                            label="Invoiced"
                            count={Number(
                                profitability.invoiced_amount.toFixed(2),
                            )}
                            amount={profitability.pending_approval_amount}
                            tone="amber"
                            countLabel="invoiced"
                            amountLabel="pending"
                        />
                        <BillingMetricCard
                            label="Gross margin"
                            count={Number(
                                profitability.gross_margin_amount.toFixed(2),
                            )}
                            amount={profitability.gross_margin_percent ?? 0}
                            tone="violet"
                            countLabel="amount"
                            amountLabel="margin %"
                        />
                    </div>

                    <div className="mt-4 grid gap-4 xl:grid-cols-2">
                        <SignalCard
                            label="Utilization"
                            value={
                                profitability.utilization_percent !== null &&
                                profitability.utilization_percent !== undefined
                                    ? `${profitability.utilization_percent.toFixed(1)}%`
                                    : '-'
                            }
                            helper={
                                profitability.remaining_hours !== null &&
                                profitability.remaining_hours !== undefined
                                    ? `${profitability.remaining_hours.toFixed(2)} hours remaining`
                                    : 'Budget hours not defined'
                            }
                            progress={profitability.utilization_percent ?? 0}
                        />
                        <SignalCard
                            label="Budget consumed"
                            value={
                                profitability.budget_consumed_percent !==
                                    null &&
                                profitability.budget_consumed_percent !==
                                    undefined
                                    ? `${profitability.budget_consumed_percent.toFixed(1)}%`
                                    : '-'
                            }
                            helper={
                                profitability.budget_amount !== null &&
                                profitability.budget_amount !== undefined
                                    ? `${profitability.actual_cost_amount.toFixed(2)} cost against ${profitability.budget_amount.toFixed(2)} budget`
                                    : 'Budget amount not defined'
                            }
                            progress={
                                profitability.budget_consumed_percent ?? 0
                            }
                        />
                        <SignalCard
                            label="Realization"
                            value={
                                profitability.realization_percent !== null &&
                                profitability.realization_percent !== undefined
                                    ? `${profitability.realization_percent.toFixed(1)}%`
                                    : '-'
                            }
                            helper={`${profitability.invoiced_amount.toFixed(2)} invoiced from ${profitability.billable_pipeline_amount.toFixed(2)} pipeline`}
                            progress={
                                profitability.realization_percent ?? 0
                            }
                        />
                        <SignalCard
                            label="Approved hours"
                            value={profitability.approved_hours.toFixed(2)}
                            helper="Timesheets already approved for this project"
                            progress={
                                profitability.logged_hours > 0
                                    ? (profitability.approved_hours /
                                          profitability.logged_hours) *
                                      100
                                    : 0
                            }
                        />
                    </div>
                </section>

                <section className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Tasks
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Task delivery status inside this project.
                                </p>
                            </div>
                            {abilities.can_create_task && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={`/company/projects/${project.id}/tasks/create`}
                                    >
                                        Add task
                                    </Link>
                                </Button>
                            )}
                        </div>

                        <div className="mt-4 overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Task</th>
                                        <th className="px-3 py-2 font-medium">Stage</th>
                                        <th className="px-3 py-2 font-medium">Status</th>
                                        <th className="px-3 py-2 font-medium">Priority</th>
                                        <th className="px-3 py-2 font-medium">Assignee</th>
                                        <th className="px-3 py-2 font-medium">Due date</th>
                                        <th className="px-3 py-2 font-medium">Hours</th>
                                        <th className="px-3 py-2 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {project.tasks.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="px-3 py-6 text-center text-muted-foreground"
                                            >
                                                No tasks created for this project yet.
                                            </td>
                                        </tr>
                                    )}
                                    {project.tasks.map((task) => (
                                        <tr key={task.id}>
                                            <td className="px-3 py-2">
                                                <p className="font-medium">
                                                    {task.task_number}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {task.title}
                                                </p>
                                            </td>
                                            <td className="px-3 py-2">
                                                {task.stage_name ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatLabel(task.status)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatLabel(task.priority)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {task.assignee_name ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {task.due_date ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {(task.actual_hours || 0).toFixed(2)} /
                                                {' '}
                                                {task.estimated_hours !== null &&
                                                task.estimated_hours !== undefined
                                                    ? task.estimated_hours.toFixed(2)
                                                    : '-'}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {task.can_edit ? (
                                                    <Link
                                                        href={`/company/projects/tasks/${task.id}/edit`}
                                                        className="font-medium text-primary"
                                                    >
                                                        Edit
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        View only
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <h2 className="text-sm font-semibold">Team</h2>
                        <p className="text-xs text-muted-foreground">
                            Users currently attached to this delivery workspace.
                        </p>

                        <div className="mt-4 space-y-3">
                            {project.members.length === 0 && (
                                <div className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                    No project members linked yet.
                                </div>
                            )}
                            {project.members.map((member) => (
                                <div
                                    key={member.id}
                                    className="rounded-lg border px-3 py-3"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {member.name ?? 'Unknown user'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {member.email ?? '-'}
                                            </p>
                                        </div>
                                        <span className="rounded-md border px-2 py-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                                            {formatLabel(member.project_role)}
                                        </span>
                                    </div>
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        Allocation{' '}
                                        {member.allocation_percent !== null &&
                                        member.allocation_percent !== undefined
                                            ? `${member.allocation_percent.toFixed(1)}%`
                                            : '-'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Timesheets
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Logged effort and approval status for this project.
                            </p>
                        </div>
                        {abilities.can_create_timesheet && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/company/projects/${project.id}/timesheets/create`}
                                >
                                    Log timesheet
                                </Link>
                            </Button>
                        )}
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[1100px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Owner</th>
                                    <th className="px-3 py-2 font-medium">Task</th>
                                    <th className="px-3 py-2 font-medium">Work date</th>
                                    <th className="px-3 py-2 font-medium">Hours</th>
                                    <th className="px-3 py-2 font-medium">Approval</th>
                                    <th className="px-3 py-2 font-medium">Invoice</th>
                                    <th className="px-3 py-2 font-medium">Cost</th>
                                    <th className="px-3 py-2 font-medium">Billable</th>
                                    <th className="px-3 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {project.timesheets.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="px-3 py-6 text-center text-muted-foreground"
                                        >
                                            No timesheets logged for this project yet.
                                        </td>
                                    </tr>
                                )}
                                {project.timesheets.map((timesheet) => (
                                    <tr key={timesheet.id}>
                                        <td className="px-3 py-2">
                                            {timesheet.user_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <p>{timesheet.task_number ?? '-'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {timesheet.task_title ?? 'No linked task'}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2">
                                            {timesheet.work_date ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {timesheet.hours.toFixed(2)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <p>{formatLabel(timesheet.approval_status)}</p>
                                            {timesheet.rejection_reason && (
                                                <p className="text-xs text-muted-foreground">
                                                    {timesheet.rejection_reason}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatLabel(timesheet.invoice_status)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {timesheet.cost_amount.toFixed(2)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {timesheet.billable_amount.toFixed(2)}
                                            <p className="text-xs text-muted-foreground">
                                                {timesheet.is_billable
                                                    ? 'Billable'
                                                    : 'Non-billable'}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="inline-flex items-center gap-3">
                                                <Link
                                                    href={`/company/projects/timesheets/${timesheet.id}/edit`}
                                                    className="font-medium text-primary"
                                                >
                                                    {timesheet.can_edit ? 'Edit' : 'Open'}
                                                </Link>
                                                {timesheet.can_submit && (
                                                    <button
                                                        type="button"
                                                        className="font-medium text-primary"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/projects/timesheets/${timesheet.id}/submit`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Submit
                                                    </button>
                                                )}
                                                {timesheet.can_approve && (
                                                    <button
                                                        type="button"
                                                        className="font-medium text-primary"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/projects/timesheets/${timesheet.id}/approve`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </button>
                                                )}
                                                {timesheet.can_reject && (
                                                    <button
                                                        type="button"
                                                        className="font-medium text-primary"
                                                        onClick={() =>
                                                            handleRejectTimesheet(
                                                                timesheet.id,
                                                                timesheet.rejection_reason,
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Milestones
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Delivery checkpoints and milestone billing readiness.
                            </p>
                        </div>
                        {abilities.can_create_milestone && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/company/projects/${project.id}/milestones/create`}
                                >
                                    Add milestone
                                </Link>
                            </Button>
                        )}
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[980px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Milestone</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Due date</th>
                                    <th className="px-3 py-2 font-medium">Completed</th>
                                    <th className="px-3 py-2 font-medium">Amount</th>
                                    <th className="px-3 py-2 font-medium">Invoice</th>
                                    <th className="px-3 py-2 font-medium">Approved by</th>
                                    <th className="px-3 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {project.milestones.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-3 py-6 text-center text-muted-foreground"
                                        >
                                            No milestones created for this project yet.
                                        </td>
                                    </tr>
                                )}
                                {project.milestones.map((milestone) => (
                                    <tr key={milestone.id}>
                                        <td className="px-3 py-2">
                                            <p className="font-medium">
                                                {String(milestone.sequence).padStart(2, '0')} -{' '}
                                                {milestone.name}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatLabel(milestone.status)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {milestone.due_date ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {milestone.completed_at
                                                ? new Date(
                                                      milestone.completed_at,
                                                  ).toLocaleString()
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {milestone.amount.toFixed(2)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatLabel(milestone.invoice_status)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <p>{milestone.approved_by_name ?? '-'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {milestone.approved_at
                                                    ? new Date(
                                                          milestone.approved_at,
                                                      ).toLocaleString()
                                                    : '-'}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {milestone.can_edit ? (
                                                <Link
                                                    href={`/company/projects/milestones/${milestone.id}/edit`}
                                                    className="font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    View only
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Project billables
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Review billable rows for this project and create
                                draft invoices from eligible items.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[1120px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        <input
                                            type="checkbox"
                                            className="size-4 rounded border-input"
                                            checked={allSelectableBillablesChecked}
                                            onChange={(event) =>
                                                setSelectedBillableIds(
                                                    event.target.checked
                                                        ? selectableBillableIds
                                                        : [],
                                                )
                                            }
                                            aria-label="Select all invoice eligible project billables"
                                        />
                                    </th>
                                    <th className="px-3 py-2 font-medium">Type</th>
                                    <th className="px-3 py-2 font-medium">Description</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Approval</th>
                                    <th className="px-3 py-2 font-medium">Amount</th>
                                    <th className="px-3 py-2 font-medium">Invoice</th>
                                    <th className="px-3 py-2 font-medium">Updated</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {project.billables.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-3 py-6 text-center text-muted-foreground"
                                        >
                                            No billables have been generated for this project yet.
                                        </td>
                                    </tr>
                                )}
                                {project.billables.map((billable) => (
                                    <tr key={billable.id}>
                                        <td className="px-3 py-2">
                                            {billable.can_create_invoice ? (
                                                <input
                                                    type="checkbox"
                                                    className="size-4 rounded border-input"
                                                    checked={selectedBillableIds.includes(
                                                        billable.id,
                                                    )}
                                                    onChange={(event) =>
                                                        setSelectedBillableIds(
                                                            (current) =>
                                                                event.target.checked
                                                                    ? current.includes(
                                                                          billable.id,
                                                                      )
                                                                        ? current
                                                                        : [
                                                                              ...current,
                                                                              billable.id,
                                                                          ]
                                                                    : current.filter(
                                                                          (id) =>
                                                                              id !==
                                                                              billable.id,
                                                                      ),
                                                        )
                                                    }
                                                    aria-label={`Select billable ${billable.id}`}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatLabel(billable.billable_type)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="max-w-[320px]">
                                                <p className="font-medium">
                                                    {billable.description ??
                                                        'No description'}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatLabel(billable.status)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {formatLabel(
                                                billable.approval_status,
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {billable.amount.toFixed(2)}
                                            <p className="text-xs text-muted-foreground">
                                                {billable.quantity.toFixed(2)} x{' '}
                                                {billable.unit_price.toFixed(2)}{' '}
                                                {billable.currency_code ?? ''}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2">
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
                                            ) : (
                                                'Not invoiced'
                                            )}
                                            {billable.invoice_status && (
                                                <p className="text-xs text-muted-foreground">
                                                    {formatLabel(
                                                        billable.invoice_status,
                                                    )}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {billable.updated_at
                                                ? new Date(
                                                      billable.updated_at,
                                                  ).toLocaleString()
                                                : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
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

function DetailRow({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="rounded-lg border px-3 py-3">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-sm font-medium">{value || '-'}</p>
        </div>
    );
}

function SummaryRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between rounded-lg border px-3 py-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function BillingMetricCard({
    label,
    count,
    amount,
    tone,
    countLabel = 'records',
    amountLabel = 'amount',
}: {
    label: string;
    count: number;
    amount: number;
    tone: 'emerald' | 'amber' | 'blue' | 'violet';
    countLabel?: string;
    amountLabel?: string;
}) {
    const toneClass =
        tone === 'emerald'
            ? 'from-emerald-500/10 to-emerald-500/0'
            : tone === 'amber'
              ? 'from-amber-500/10 to-amber-500/0'
              : tone === 'violet'
                ? 'from-violet-500/10 to-violet-500/0'
                : 'from-blue-500/10 to-blue-500/0';

    return (
        <div className={`rounded-lg border bg-gradient-to-br ${toneClass} px-4 py-4`}>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="mt-3 flex items-end justify-between gap-3">
                <div>
                    <p className="text-2xl font-semibold">{count}</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {countLabel}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-sm font-medium">{amount.toFixed(2)}</p>
                    <p className="mt-1 text-[11px] text-muted-foreground">
                        {amountLabel}
                    </p>
                </div>
            </div>
        </div>
    );
}

function SignalCard({
    label,
    value,
    helper,
    progress,
}: {
    label: string;
    value: string;
    helper: string;
    progress: number;
}) {
    const normalizedProgress = Math.max(0, Math.min(progress, 100));

    return (
        <div className="rounded-lg border px-4 py-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
            <div className="mt-4 h-2 rounded-full bg-muted">
                <div
                    className="h-2 rounded-full bg-primary/80"
                    style={{ width: `${normalizedProgress}%` }}
                />
            </div>
            <p className="mt-3 text-xs text-muted-foreground">{helper}</p>
        </div>
    );
}
