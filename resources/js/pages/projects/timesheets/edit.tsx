import { Head, Link, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

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
        id: string;
        user_id?: string | null;
        user_name?: string | null;
        task_id?: string | null;
        task_number?: string | null;
        task_title?: string | null;
        work_date?: string | null;
        description?: string | null;
        hours: string;
        is_billable: boolean;
        cost_rate: string;
        bill_rate: string;
        cost_amount: number;
        billable_amount: number;
        approval_status: string;
        approved_by_name?: string | null;
        approved_at?: string | null;
        rejection_reason?: string | null;
        invoice_status: string;
        updated_at?: string | null;
    };
    taskOptions: TaskOption[];
    userOptions: UserOption[];
    abilities: {
        can_manage_team_timesheets: boolean;
        can_edit_timesheet: boolean;
        can_submit_timesheet: boolean;
        can_approve_timesheet: boolean;
        can_reject_timesheet: boolean;
        can_delete_timesheet: boolean;
    };
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

export default function ProjectTimesheetEdit({
    project,
    timesheet,
    taskOptions,
    userOptions,
    abilities,
}: Props) {
    const form = useForm({
        user_id: timesheet.user_id ?? '',
        task_id: timesheet.task_id ?? '',
        work_date: timesheet.work_date ?? '',
        description: timesheet.description ?? '',
        hours: timesheet.hours,
        is_billable: timesheet.is_billable,
        cost_rate: timesheet.cost_rate,
        bill_rate: timesheet.bill_rate,
    });

    const selectedUserName =
        userOptions.find((option) => option.id === form.data.user_id)?.name ??
        timesheet.user_name ??
        'Current user';

    const editable = abilities.can_edit_timesheet;

    const handleReject = () => {
        const reason =
            window.prompt('Optional rejection reason', timesheet.rejection_reason ?? '') ?? '';

        router.post(
            `/company/projects/timesheets/${timesheet.id}/reject`,
            { reason },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, { title: 'Workspace', href: '/company/projects/workspace' },
                {
                    title: project.project_code,
                    href: `/company/projects/${project.id}`,
                },
                {
                    title: 'Edit Timesheet',
                    href: `/company/projects/timesheets/${timesheet.id}/edit`,
                },)}
        >
            <Head title="Edit Project Timesheet" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit timesheet</h1>
                    <p className="text-sm text-muted-foreground">
                        Review effort, approval state, and billing readiness for{' '}
                        {project.name}.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={`/company/projects/${project.id}`}>
                            Open project
                        </Link>
                    </Button>
                <BackLinkAction
                    href={`/company/projects/${project.id}`}
                    label="Back to project"
                    variant="ghost"
                />
                </div>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/projects/timesheets/${timesheet.id}`);
                }}
            >
                <div className="rounded-xl border p-4 text-sm">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Approval status
                            </p>
                            <p className="mt-1 font-medium">
                                {formatLabel(timesheet.approval_status)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Invoice status
                            </p>
                            <p className="mt-1 font-medium">
                                {formatLabel(timesheet.invoice_status)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Cost amount
                            </p>
                            <p className="mt-1 font-medium">
                                {timesheet.cost_amount.toFixed(2)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Billable amount
                            </p>
                            <p className="mt-1 font-medium">
                                {timesheet.billable_amount.toFixed(2)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Approved by
                            </p>
                            <p className="mt-1 font-medium">
                                {timesheet.approved_by_name ?? '-'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Approved at
                            </p>
                            <p className="mt-1 font-medium">
                                {formatDateTime(timesheet.approved_at)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Last updated
                            </p>
                            <p className="mt-1 font-medium">
                                {formatDateTime(timesheet.updated_at)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Rejection reason
                            </p>
                            <p className="mt-1 font-medium">
                                {timesheet.rejection_reason ?? '-'}
                            </p>
                        </div>
                    </div>
                </div>

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
                                disabled={!editable}
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
                                {selectedUserName}
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
                            disabled={!editable}
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
                            disabled={!editable}
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
                            disabled={!editable}
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
                            disabled={!editable}
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
                                    disabled={!editable}
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
                                    disabled={!editable || !form.data.is_billable}
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
                            disabled={!editable}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {abilities.can_edit_timesheet && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}
                    {abilities.can_submit_timesheet && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/company/projects/timesheets/${timesheet.id}/submit`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Submit for approval
                        </Button>
                    )}
                    {abilities.can_approve_timesheet && (
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(
                                    `/company/projects/timesheets/${timesheet.id}/approve`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Approve
                        </Button>
                    )}
                    {abilities.can_reject_timesheet && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleReject}
                        >
                            Reject
                        </Button>
                    )}
                    {abilities.can_delete_timesheet && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(
                                    `/company/projects/timesheets/${timesheet.id}`,
                                )
                            }
                        >
                            Delete timesheet
                        </Button>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}
