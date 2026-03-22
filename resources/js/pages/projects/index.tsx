import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

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
    recentProjects: ProjectRow[];
    recentTasks: TaskRow[];
    abilities: {
        can_create_project: boolean;
        can_view_tasks: boolean;
        can_view_billables: boolean;
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectsDashboard({
    kpis,
    recentProjects,
    recentTasks,
    abilities,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Projects', href: '/company/projects' },
            ]}
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
                            <table className="w-full min-w-[920px] text-sm">
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
                                                colSpan={8}
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
