import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type TaskOption = {
    id: string;
    task_number: string;
    title: string;
    assignee_name?: string | null;
};

type UserOption = {
    id: string;
    name: string;
    email: string;
    role_name?: string | null;
};

type Props = {
    project: {
        id: string;
        project_code: string;
        name: string;
    };
    timesheet: {
        user_id: string;
        task_id: string;
        work_date: string;
        description: string;
        hours: string;
        is_billable: boolean;
        cost_rate: string;
        bill_rate: string;
    };
    taskOptions: TaskOption[];
    userOptions: UserOption[];
    abilities: {
        can_manage_team_timesheets: boolean;
    };
};

export default function ProjectTimesheetCreate({
    project,
    timesheet,
    taskOptions,
    userOptions,
    abilities,
}: Props) {
    const form = useForm({
        user_id: timesheet.user_id,
        task_id: timesheet.task_id,
        work_date: timesheet.work_date,
        description: timesheet.description,
        hours: timesheet.hours,
        is_billable: timesheet.is_billable,
        cost_rate: timesheet.cost_rate,
        bill_rate: timesheet.bill_rate,
    });

    const currentUserName =
        userOptions.find((option) => option.id === form.data.user_id)?.name ??
        'Current user';

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
                    title: 'New Timesheet',
                    href: `/company/projects/${project.id}/timesheets/create`,
                },
            ]}
        >
            <Head title="New Project Timesheet" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New timesheet</h1>
                    <p className="text-sm text-muted-foreground">
                        Log delivery effort against {project.name}.
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
                    form.post(`/company/projects/${project.id}/timesheets`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    {abilities.can_manage_team_timesheets ? (
                        <div className="grid gap-2">
                            <Label htmlFor="user_id">Timesheet owner</Label>
                            <select
                                id="user_id"
                                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                                value={form.data.user_id}
                                onChange={(event) =>
                                    form.setData('user_id', event.target.value)
                                }
                            >
                                <option value="">Select user</option>
                                {userOptions.map((userOption) => (
                                    <option
                                        key={userOption.id}
                                        value={userOption.id}
                                    >
                                        {userOption.name}
                                        {userOption.role_name
                                            ? ` - ${userOption.role_name}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.user_id} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label>Timesheet owner</Label>
                            <div className="flex h-9 items-center rounded-md border px-3 text-sm">
                                {currentUserName}
                            </div>
                        </div>
                    )}

                    <div className="grid gap-2 md:col-span-2 xl:col-span-2">
                        <Label htmlFor="task_id">Task</Label>
                        <select
                            id="task_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.task_id}
                            onChange={(event) =>
                                form.setData('task_id', event.target.value)
                            }
                        >
                            <option value="">No linked task</option>
                            {taskOptions.map((taskOption) => (
                                <option key={taskOption.id} value={taskOption.id}>
                                    {taskOption.task_number} - {taskOption.title}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.task_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="work_date">Work date</Label>
                        <Input
                            id="work_date"
                            type="date"
                            value={form.data.work_date}
                            onChange={(event) =>
                                form.setData('work_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.work_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="hours">Hours</Label>
                        <Input
                            id="hours"
                            type="number"
                            min={0.25}
                            step="0.25"
                            value={form.data.hours}
                            onChange={(event) =>
                                form.setData('hours', event.target.value)
                            }
                        />
                        <InputError message={form.errors.hours} />
                    </div>

                    <div className="flex items-center gap-3 rounded-lg border px-3 py-3 md:col-span-2 xl:col-span-4">
                        <input
                            id="is_billable"
                            type="checkbox"
                            checked={form.data.is_billable}
                            onChange={(event) =>
                                form.setData('is_billable', event.target.checked)
                            }
                        />
                        <Label htmlFor="is_billable">
                            Mark this entry as billable work
                        </Label>
                    </div>

                    {abilities.can_manage_team_timesheets && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="cost_rate">Cost rate</Label>
                                <Input
                                    id="cost_rate"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.cost_rate}
                                    onChange={(event) =>
                                        form.setData(
                                            'cost_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.cost_rate} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bill_rate">Bill rate</Label>
                                <Input
                                    id="bill_rate"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.bill_rate}
                                    onChange={(event) =>
                                        form.setData(
                                            'bill_rate',
                                            event.target.value,
                                        )
                                    }
                                    disabled={!form.data.is_billable}
                                />
                                <InputError message={form.errors.bill_rate} />
                            </div>
                        </>
                    )}

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
                        Create timesheet
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
