import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type StageOption = {
    id: string;
    name: string;
};

type AssigneeOption = {
    id: string;
    name: string;
    email: string;
    role_name?: string | null;
};

type CustomerOption = {
    id: string;
    name: string;
};

type ParentTaskOption = {
    id: string;
    task_number: string;
    title: string;
};

type Props = {
    project: {
        id: string;
        project_code: string;
        name: string;
        customer_id?: string | null;
    };
    task: {
        task_number: string;
        title: string;
        description: string;
        stage_id: string;
        parent_task_id: string;
        customer_id: string;
        status: string;
        priority: string;
        assigned_to: string;
        start_date: string;
        due_date: string;
        estimated_hours: string;
        is_billable: boolean;
        billing_status: string;
    };
    stages: StageOption[];
    assignees: AssigneeOption[];
    customers: CustomerOption[];
    parentTasks: ParentTaskOption[];
    statuses: string[];
    priorities: string[];
    billingStatuses: string[];
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectTaskCreate({
    project,
    task,
    stages,
    assignees,
    customers,
    parentTasks,
    statuses,
    priorities,
    billingStatuses,
}: Props) {
    const form = useForm({
        task_number: task.task_number,
        title: task.title,
        description: task.description,
        stage_id: task.stage_id,
        parent_task_id: task.parent_task_id,
        customer_id: task.customer_id,
        status: task.status,
        priority: task.priority,
        assigned_to: task.assigned_to,
        start_date: task.start_date,
        due_date: task.due_date,
        estimated_hours: task.estimated_hours,
        is_billable: task.is_billable,
        billing_status: task.billing_status,
    });

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
                {
                    title: 'New Task',
                    href: `/company/projects/${project.id}/tasks/create`,
                },
            ]}
        >
            <Head title="New Project Task" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New task</h1>
                    <p className="text-sm text-muted-foreground">
                        Add scoped execution work to {project.name}.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href={`/company/projects/${project.id}`}>Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/company/projects/${project.id}/tasks`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="task_number">Task number</Label>
                        <Input
                            id="task_number"
                            value={form.data.task_number}
                            onChange={(event) =>
                                form.setData('task_number', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.task_number} />
                    </div>

                    <div className="grid gap-2 md:col-span-2 xl:col-span-3">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="stage_id">Stage</Label>
                        <select
                            id="stage_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.stage_id}
                            onChange={(event) =>
                                form.setData('stage_id', event.target.value)
                            }
                        >
                            {stages.map((stage) => (
                                <option key={stage.id} value={stage.id}>
                                    {stage.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.stage_id} />
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
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {formatLabel(status)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.status} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="priority">Priority</Label>
                        <select
                            id="priority"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.priority}
                            onChange={(event) =>
                                form.setData('priority', event.target.value)
                            }
                        >
                            {priorities.map((priority) => (
                                <option key={priority} value={priority}>
                                    {formatLabel(priority)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.priority} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="assigned_to">Assignee</Label>
                        <select
                            id="assigned_to"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.assigned_to}
                            onChange={(event) =>
                                form.setData('assigned_to', event.target.value)
                            }
                        >
                            <option value="">Unassigned</option>
                            {assignees.map((assignee) => (
                                <option key={assignee.id} value={assignee.id}>
                                    {assignee.name}
                                    {assignee.role_name
                                        ? ` - ${assignee.role_name}`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.assigned_to} />
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
                            <option value="">No linked customer</option>
                            {customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.customer_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="parent_task_id">Parent task</Label>
                        <select
                            id="parent_task_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.parent_task_id}
                            onChange={(event) =>
                                form.setData(
                                    'parent_task_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">No parent task</option>
                            {parentTasks.map((parentTask) => (
                                <option
                                    key={parentTask.id}
                                    value={parentTask.id}
                                >
                                    {parentTask.task_number} -{' '}
                                    {parentTask.title}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.parent_task_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="billing_status">Billing status</Label>
                        <select
                            id="billing_status"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.billing_status}
                            onChange={(event) =>
                                form.setData(
                                    'billing_status',
                                    event.target.value,
                                )
                            }
                        >
                            {billingStatuses.map((billingStatus) => (
                                <option
                                    key={billingStatus}
                                    value={billingStatus}
                                >
                                    {formatLabel(billingStatus)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.billing_status} />
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
                        <InputError message={form.errors.start_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="due_date">Due date</Label>
                        <Input
                            id="due_date"
                            type="date"
                            value={form.data.due_date}
                            onChange={(event) =>
                                form.setData('due_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.due_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="estimated_hours">Estimated hours</Label>
                        <Input
                            id="estimated_hours"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.estimated_hours}
                            onChange={(event) =>
                                form.setData(
                                    'estimated_hours',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.estimated_hours} />
                    </div>

                    <div className="flex items-center gap-3 rounded-lg border px-3 py-3 md:col-span-2 xl:col-span-4">
                        <input
                            id="is_billable"
                            type="checkbox"
                            checked={form.data.is_billable}
                            onChange={(event) =>
                                form.setData(
                                    'is_billable',
                                    event.target.checked,
                                )
                            }
                        />
                        <Label htmlFor="is_billable">Mark as billable work</Label>
                    </div>

                    <div className="grid gap-2 md:col-span-2 xl:col-span-4">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            className="min-h-28 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Create task
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
