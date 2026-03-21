import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

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
    };
    summary: {
        task_total: number;
        task_completed: number;
        task_overdue: number;
        team_members: number;
        timesheets_logged: number;
        billables_logged: number;
    };
    abilities: {
        can_edit_project: boolean;
        can_create_task: boolean;
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectShow({ project, summary, abilities }: Props) {
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
                                label="Billables logged"
                                value={summary.billables_logged}
                            />
                            <SummaryRow
                                label="Open tasks"
                                value={summary.task_total - summary.task_completed}
                            />
                        </div>
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
