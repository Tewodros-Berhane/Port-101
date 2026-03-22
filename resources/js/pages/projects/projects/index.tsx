import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type ProjectRow = {
    id: string;
    project_code: string;
    name: string;
    status: string;
    billing_type: string;
    health_status: string;
    customer_name?: string | null;
    project_manager_name?: string | null;
    progress_percent: number;
    target_end_date?: string | null;
    budget_amount?: number | null;
    tasks_count: number;
    can_view: boolean;
    can_edit: boolean;
};

type Props = {
    filters: {
        search: string;
        status: string;
        billing_type: string;
    };
    statuses: string[];
    billingTypes: string[];
    projects: {
        data: ProjectRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    abilities: {
        can_create_project: boolean;
        can_view_billables: boolean;
        can_view_recurring: boolean;
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectsIndex({
    filters,
    statuses,
    billingTypes,
    projects,
    abilities,
}: Props) {
    const form = useForm({
        search: filters.search,
        status: filters.status,
        billing_type: filters.billing_type,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Projects', href: '/company/projects' },
                { title: 'Workspace', href: '/company/projects/workspace' },
            ]}
        >
            <Head title="Projects Workspace" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Projects workspace</h1>
                    <p className="text-sm text-muted-foreground">
                        Search, review, and manage project delivery records.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/projects">Dashboard</Link>
                    </Button>
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
                            <Link href="/company/projects/create">New project</Link>
                        </Button>
                    )}
                </div>
            </div>

            <form
                className="mt-6 grid gap-4 rounded-xl border p-4 md:grid-cols-3 xl:grid-cols-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/company/projects/workspace', {
                        preserveState: true,
                        replace: true,
                    });
                }}
            >
                <div className="grid gap-2 xl:col-span-2">
                    <Label htmlFor="search">Search</Label>
                    <Input
                        id="search"
                        value={form.data.search}
                        onChange={(event) =>
                            form.setData('search', event.target.value)
                        }
                        placeholder="Project code or name"
                    />
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
                    <Label htmlFor="billing_type">Billing type</Label>
                    <select
                        id="billing_type"
                        className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                        value={form.data.billing_type}
                        onChange={(event) =>
                            form.setData('billing_type', event.target.value)
                        }
                    >
                        <option value="">All billing types</option>
                        {billingTypes.map((billingType) => (
                            <option key={billingType} value={billingType}>
                                {formatLabel(billingType)}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex flex-wrap items-end gap-2 xl:col-span-4">
                    <Button type="submit">Apply filters</Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            form.setData({
                                search: '',
                                status: '',
                                billing_type: '',
                            });
                            form.get('/company/projects/workspace', {
                                data: {
                                    search: '',
                                    status: '',
                                    billing_type: '',
                                },
                                preserveState: true,
                                replace: true,
                            });
                        }}
                    >
                        Reset
                    </Button>
                </div>
            </form>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1180px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Project</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Billing</th>
                            <th className="px-4 py-3 font-medium">Health</th>
                            <th className="px-4 py-3 font-medium">Customer</th>
                            <th className="px-4 py-3 font-medium">Manager</th>
                            <th className="px-4 py-3 font-medium">Tasks</th>
                            <th className="px-4 py-3 font-medium">Budget</th>
                            <th className="px-4 py-3 font-medium">Progress</th>
                            <th className="px-4 py-3 font-medium">Target end</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {projects.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={11}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No projects match the current filters.
                                </td>
                            </tr>
                        )}
                        {projects.data.map((project) => (
                            <tr key={project.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium">
                                        {project.project_code}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {project.name}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    {formatLabel(project.status)}
                                </td>
                                <td className="px-4 py-3">
                                    {formatLabel(project.billing_type)}
                                </td>
                                <td className="px-4 py-3">
                                    {formatLabel(project.health_status)}
                                </td>
                                <td className="px-4 py-3">
                                    {project.customer_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {project.project_manager_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">{project.tasks_count}</td>
                                <td className="px-4 py-3">
                                    {project.budget_amount !== null &&
                                    project.budget_amount !== undefined
                                        ? project.budget_amount.toFixed(2)
                                        : '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {project.progress_percent.toFixed(1)}%
                                </td>
                                <td className="px-4 py-3">
                                    {project.target_end_date ?? '-'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="inline-flex items-center gap-3">
                                        {project.can_view && (
                                            <Link
                                                href={`/company/projects/${project.id}`}
                                                className="font-medium text-primary"
                                            >
                                                Open
                                            </Link>
                                        )}
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

            {projects.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {projects.links.map((link) => (
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
