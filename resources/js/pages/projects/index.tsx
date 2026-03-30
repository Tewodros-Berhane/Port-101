import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type ProjectRow = {
    id: string;
    project_code: string;
    name: string;
    status: string;
    health_status: string;
    customer_name?: string | null;
    project_manager_name?: string | null;
    progress_percent: number;
    target_end_date?: string | null;
    ready_to_invoice_amount: number;
    invoiced_amount: number;
    gross_margin_percent?: number | null;
    utilization_percent?: number | null;
    can_edit: boolean;
};

type TaskRow = {
    id: string;
    task_number: string;
    title: string;
    status: string;
    priority: string;
    project_id: string;
    project_name?: string | null;
    project_code?: string | null;
    assignee_name?: string | null;
    due_date?: string | null;
    can_edit: boolean;
};

type Props = {
    kpis: {
        total_projects: number;
        active_projects: number;
        at_risk_projects: number;
        overdue_projects: number;
        tasks_due_7d: number;
        unassigned_tasks: number;
    };
    profitability: {
        total_budget_hours: number;
        total_logged_hours: number;
        utilization_percent?: number | null;
        billable_pipeline_amount: number;
        ready_to_invoice_amount: number;
        pending_approval_amount: number;
        invoiced_amount: number;
        gross_margin_amount: number;
        gross_margin_percent?: number | null;
        negative_margin_projects: number;
        over_budget_hour_projects: number;
    };
    recurring: {
        active_count: number;
        due_now_count: number;
        auto_invoice_count: number;
        active_recurring_amount: number;
    };
    recentProjects: ProjectRow[];
    recentTasks: TaskRow[];
    abilities: {
        can_create_project: boolean;
        can_view_tasks: boolean;
        can_view_billables: boolean;
        can_view_recurring: boolean;
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectsDashboard({
    kpis,
    profitability,
    recurring,
    recentProjects,
    recentTasks,
    abilities,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, )}
        >
            <Head title="Projects" />

            <div className="space-y-6">
                <section className="rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-chart-2/10 via-transparent to-chart-4/10 p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Projects workspace
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Delivery tracking for implementation, service,
                                and billable execution work.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {abilities.can_view_billables && (
                                <Button variant="outline" asChild>
                                    <Link href="/company/projects/billables">
                                        Billing queue
                                    </Link>
                                </Button>
                            )}
                            {abilities.can_view_recurring && (
                                <Button variant="outline" asChild>
                                    <Link href="/company/projects/recurring-billing">
                                        Recurring billing
                                    </Link>
                                </Button>
                            )}
                            {abilities.can_create_project && (
                                <Button asChild>
                                    <Link href="/company/projects/create">
                                        New project
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link href="/company/projects/workspace">
                                    Open workspace
                                </Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <MetricCard
                        label="Total projects"
                        value={String(kpis.total_projects)}
                    />
                    <MetricCard
                        label="Active projects"
                        value={String(kpis.active_projects)}
                    />
                    <MetricCard
                        label="At risk"
                        value={String(kpis.at_risk_projects)}
                    />
                    <MetricCard
                        label="Overdue"
                        value={String(kpis.overdue_projects)}
                    />
                    <MetricCard
                        label="Tasks due in 7 days"
                        value={String(kpis.tasks_due_7d)}
                    />
                    <MetricCard
                        label="Unassigned tasks"
                        value={String(kpis.unassigned_tasks)}
                    />
                </section>

                <section className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Profitability overview
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Revenue pipeline, invoicing progress, and margin
                                signals across accessible projects.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <span className="rounded-md border px-2 py-1">
                                Budget hours {profitability.total_budget_hours.toFixed(2)}
                            </span>
                            <span className="rounded-md border px-2 py-1">
                                Logged hours {profitability.total_logged_hours.toFixed(2)}
                            </span>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <ProfitabilityCard
                            label="Billable pipeline"
                            value={profitability.billable_pipeline_amount.toFixed(
                                2,
                            )}
                            helper="All non-cancelled billables"
                            tone="blue"
                        />
                        <ProfitabilityCard
                            label="Ready to invoice"
                            value={profitability.ready_to_invoice_amount.toFixed(
                                2,
                            )}
                            helper="Approved or approval-free billables"
                            tone="emerald"
                        />
                        <ProfitabilityCard
                            label="Pending approval"
                            value={profitability.pending_approval_amount.toFixed(
                                2,
                            )}
                            helper="Billables awaiting review"
                            tone="amber"
                        />
                        <ProfitabilityCard
                            label="Invoiced"
                            value={profitability.invoiced_amount.toFixed(2)}
                            helper="Already handed off to Accounting"
                            tone="slate"
                        />
                        <ProfitabilityCard
                            label="Gross margin"
                            value={`${profitability.gross_margin_amount.toFixed(2)}${
                                profitability.gross_margin_percent !== null &&
                                profitability.gross_margin_percent !== undefined
                                    ? ` • ${profitability.gross_margin_percent.toFixed(1)}%`
                                    : ''
                            }`}
                            helper={`${profitability.negative_margin_projects} negative-margin projects`}
                            tone="violet"
                        />
                        <ProfitabilityCard
                            label="Utilization"
                            value={
                                profitability.utilization_percent !== null &&
                                profitability.utilization_percent !== undefined
                                    ? `${profitability.utilization_percent.toFixed(1)}%`
                                    : '-'
                            }
                            helper={`${profitability.over_budget_hour_projects} projects over budgeted hours`}
                            tone="rose"
                        />
                    </div>
                </section>

                {abilities.can_view_recurring && (
                    <section className="rounded-xl border p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Recurring billing
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Service retainers and repeat invoice cadence
                                    across the current projects portfolio.
                                </p>
                            </div>
                            <Button variant="ghost" asChild>
                                <Link href="/company/projects/recurring-billing">
                                    Open schedules
                                </Link>
                            </Button>
                        </div>

                        <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <ProfitabilityCard
                                label="Active schedules"
                                value={String(recurring.active_count)}
                                helper="Live recurring contracts"
                                tone="blue"
                            />
                            <ProfitabilityCard
                                label="Due now"
                                value={String(recurring.due_now_count)}
                                helper="Cycles ready to run"
                                tone="amber"
                            />
                            <ProfitabilityCard
                                label="Auto invoice"
                                value={String(recurring.auto_invoice_count)}
                                helper="Schedules that draft invoices automatically"
                                tone="emerald"
                            />
                            <ProfitabilityCard
                                label="Active recurring amount"
                                value={recurring.active_recurring_amount.toFixed(2)}
                                helper="Per scheduled cycle"
                                tone="violet"
                            />
                        </div>
                    </section>
                )}

                <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Recent projects
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    The latest project records across your
                                    accessible workspace.
                                </p>
                            </div>
                            <Button variant="ghost" asChild>
                                <Link href="/company/projects/workspace">
                                    View all
                                </Link>
                            </Button>
                        </div>

                        <div className="mt-4 overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[1220px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Project
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Health
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Customer
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Manager
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Progress
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Ready
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Invoiced
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Margin
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Target end
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentProjects.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={11}
                                                className="px-3 py-6 text-center text-muted-foreground"
                                            >
                                                No projects yet.
                                            </td>
                                        </tr>
                                    )}
                                    {recentProjects.map((project) => (
                                        <tr key={project.id}>
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={`/company/projects/${project.id}`}
                                                    className="font-medium text-primary"
                                                >
                                                    {project.project_code}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {project.name}
                                                </p>
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatLabel(project.status)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatLabel(
                                                    project.health_status,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.customer_name ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.project_manager_name ??
                                                    '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.progress_percent.toFixed(
                                                    1,
                                                )}
                                                %
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.ready_to_invoice_amount.toFixed(
                                                    2,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.invoiced_amount.toFixed(
                                                    2,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.gross_margin_percent !==
                                                    null &&
                                                project.gross_margin_percent !==
                                                    undefined
                                                    ? `${project.gross_margin_percent.toFixed(1)}%`
                                                    : '-'}
                                                <p className="text-xs text-muted-foreground">
                                                    Utilization{' '}
                                                    {project.utilization_percent !==
                                                        null &&
                                                    project.utilization_percent !==
                                                        undefined
                                                        ? `${project.utilization_percent.toFixed(1)}%`
                                                        : '-'}
                                                </p>
                                            </td>
                                            <td className="px-3 py-2">
                                                {project.target_end_date ?? '-'}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="inline-flex items-center gap-3">
                                                    <Link
                                                        href={`/company/projects/${project.id}`}
                                                        className="font-medium text-primary"
                                                    >
                                                        Open
                                                    </Link>
                                                    {project.can_edit && (
                                                        <Link
                                                            href={`/company/projects/${project.id}/edit`}
                                                            className="font-medium text-primary"
                                                        >
                                                            Edit
                                                        </Link>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Recent tasks
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Task updates across active delivery work.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            {!abilities.can_view_tasks && (
                                <div className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                    Task visibility is not enabled for this role.
                                </div>
                            )}
                            {abilities.can_view_tasks &&
                                recentTasks.length === 0 && (
                                    <div className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
                                        No recent tasks yet.
                                    </div>
                                )}
                            {recentTasks.map((task) => (
                                <div
                                    key={task.id}
                                    className="rounded-xl border px-3 py-3"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {task.task_number} - {task.title}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {(task.project_code ?? 'Project') +
                                                    ' - ' +
                                                    (task.project_name ?? '-')}
                                            </p>
                                        </div>
                                        <span className="rounded-md border px-2 py-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                                            {formatLabel(task.status)}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span>
                                            Priority {formatLabel(task.priority)}
                                        </span>
                                        <span>
                                            Assignee {task.assignee_name ?? '-'}
                                        </span>
                                        <span>Due {task.due_date ?? '-'}</span>
                                    </div>
                                    <div className="mt-3 flex items-center gap-3 text-sm">
                                        {task.can_edit && (
                                            <Link
                                                href={`/company/projects/tasks/${task.id}/edit`}
                                                className="font-medium text-primary"
                                            >
                                                Edit task
                                            </Link>
                                        )}
                                        <Link
                                            href={`/company/projects/${task.project_id}`}
                                            className="font-medium text-primary"
                                        >
                                            Open project
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
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
            <p className="mt-2 text-3xl font-semibold">{value}</p>
        </div>
    );
}

function ProfitabilityCard({
    label,
    value,
    helper,
    tone,
}: {
    label: string;
    value: string;
    helper: string;
    tone: 'blue' | 'emerald' | 'amber' | 'slate' | 'violet' | 'rose';
}) {
    const toneClass =
        tone === 'blue'
            ? 'from-blue-500/10 to-blue-500/0'
            : tone === 'emerald'
              ? 'from-emerald-500/10 to-emerald-500/0'
              : tone === 'amber'
                ? 'from-amber-500/10 to-amber-500/0'
                : tone === 'slate'
                  ? 'from-slate-500/10 to-slate-500/0'
                  : tone === 'violet'
                    ? 'from-violet-500/10 to-violet-500/0'
                    : 'from-rose-500/10 to-rose-500/0';

    return (
        <div className={`rounded-lg border bg-gradient-to-br ${toneClass} px-4 py-4`}>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-3 text-2xl font-semibold">{value}</p>
            <p className="mt-2 text-xs text-muted-foreground">{helper}</p>
        </div>
    );
}
