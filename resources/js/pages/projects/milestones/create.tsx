import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Props = {
    project: {
        id: string;
        project_code: string;
        name: string;
    };
    milestone: {
        name: string;
        description: string;
        sequence: string;
        status: string;
        due_date: string;
        amount: string;
    };
    statusOptions: string[];
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectMilestoneCreate({
    project,
    milestone,
    statusOptions,
}: Props) {
    const form = useForm({
        name: milestone.name,
        description: milestone.description,
        sequence: milestone.sequence,
        status: milestone.status,
        due_date: milestone.due_date,
        amount: milestone.amount,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, { title: 'Workspace', href: '/company/projects/workspace' },
                {
                    title: project.project_code,
                    href: `/company/projects/${project.id}`,
                },
                {
                    title: 'New Milestone',
                    href: `/company/projects/${project.id}/milestones/create`,
                },)}
        >
            <Head title="New Project Milestone" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New milestone</h1>
                    <p className="text-sm text-muted-foreground">
                        Track delivery checkpoints and billing readiness for{' '}
                        {project.name}.
                    </p>
                </div>
                <BackLinkAction
                    href={`/company/projects/${project.id}`}
                    label="Back to project"
                    variant="ghost"
                />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/company/projects/${project.id}/milestones`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2 md:col-span-2 xl:col-span-2">
                        <Label htmlFor="name">Milestone name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="sequence">Sequence</Label>
                        <Input
                            id="sequence"
                            type="number"
                            min={1}
                            step={1}
                            value={form.data.sequence}
                            onChange={(event) =>
                                form.setData('sequence', event.target.value)
                            }
                        />
                        <InputError message={form.errors.sequence} />
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
                            {statusOptions.map((status) => (
                                <option key={status} value={status}>
                                    {formatLabel(status)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.status} />
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
                        <Label htmlFor="amount">Amount</Label>
                        <Input
                            id="amount"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                        />
                        <InputError message={form.errors.amount} />
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
                        Create milestone
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
