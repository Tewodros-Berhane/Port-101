import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type CustomerOption = {
    id: string;
    name: string;
};

type SalesOrderOption = {
    id: string;
    order_number: string;
    partner_name?: string | null;
    status: string;
};

type CurrencyOption = {
    id: string;
    code: string;
    name: string;
};

type ProjectManagerOption = {
    id: string;
    name: string;
    email: string;
    role_name?: string | null;
};

type Props = {
    project: {
        project_code: string;
        name: string;
        description: string;
        customer_id: string;
        sales_order_id: string;
        currency_id: string;
        status: string;
        billing_type: string;
        project_manager_id: string;
        start_date: string;
        target_end_date: string;
        budget_amount: string;
        budget_hours: string;
        progress_percent: string;
        health_status: string;
    };
    customers: CustomerOption[];
    salesOrders: SalesOrderOption[];
    currencies: CurrencyOption[];
    projectManagers: ProjectManagerOption[];
    statuses: string[];
    billingTypes: string[];
    healthStatuses: string[];
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectCreate({
    project,
    customers,
    salesOrders,
    currencies,
    projectManagers,
    statuses,
    billingTypes,
    healthStatuses,
}: Props) {
    const form = useForm({
        project_code: project.project_code,
        name: project.name,
        description: project.description,
        customer_id: project.customer_id,
        sales_order_id: project.sales_order_id,
        currency_id: project.currency_id,
        status: project.status,
        billing_type: project.billing_type,
        project_manager_id: project.project_manager_id,
        start_date: project.start_date,
        target_end_date: project.target_end_date,
        budget_amount: project.budget_amount,
        budget_hours: project.budget_hours,
        progress_percent: project.progress_percent,
        health_status: project.health_status,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, { title: 'Workspace', href: '/company/projects/workspace' },
                { title: 'Create', href: '/company/projects/create' },)}
        >
            <Head title="New Project" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New project</h1>
                    <p className="text-sm text-muted-foreground">
                        Set up the delivery workspace, manager, budget, and
                        billing posture.
                    </p>
                </div>
                <BackLinkAction href="/company/projects/workspace" label="Back to workspace" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/projects');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="project_code">Project code</Label>
                        <Input
                            id="project_code"
                            value={form.data.project_code}
                            onChange={(event) =>
                                form.setData('project_code', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.project_code} />
                    </div>

                    <div className="grid gap-2 md:col-span-2 xl:col-span-3">
                        <Label htmlFor="name">Project name</Label>
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
                        <Label htmlFor="sales_order_id">Sales order</Label>
                        <select
                            id="sales_order_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.sales_order_id}
                            onChange={(event) =>
                                form.setData(
                                    'sales_order_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">No linked sales order</option>
                            {salesOrders.map((order) => (
                                <option key={order.id} value={order.id}>
                                    {order.order_number}
                                    {order.partner_name
                                        ? ` - ${order.partner_name}`
                                        : ''}
                                    {` (${formatLabel(order.status)})`}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.sales_order_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="currency_id">Currency</Label>
                        <select
                            id="currency_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.currency_id}
                            onChange={(event) =>
                                form.setData('currency_id', event.target.value)
                            }
                        >
                            {currencies.map((currency) => (
                                <option key={currency.id} value={currency.id}>
                                    {currency.code} - {currency.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.currency_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="project_manager_id">
                            Project manager
                        </Label>
                        <select
                            id="project_manager_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.project_manager_id}
                            onChange={(event) =>
                                form.setData(
                                    'project_manager_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Select manager</option>
                            {projectManagers.map((manager) => (
                                <option key={manager.id} value={manager.id}>
                                    {manager.name}
                                    {manager.role_name
                                        ? ` - ${manager.role_name}`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.project_manager_id} />
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
                        <Label htmlFor="billing_type">Billing type</Label>
                        <select
                            id="billing_type"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.billing_type}
                            onChange={(event) =>
                                form.setData('billing_type', event.target.value)
                            }
                        >
                            {billingTypes.map((billingType) => (
                                <option key={billingType} value={billingType}>
                                    {formatLabel(billingType)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.billing_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="health_status">Health</Label>
                        <select
                            id="health_status"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.health_status}
                            onChange={(event) =>
                                form.setData('health_status', event.target.value)
                            }
                        >
                            {healthStatuses.map((healthStatus) => (
                                <option
                                    key={healthStatus}
                                    value={healthStatus}
                                >
                                    {formatLabel(healthStatus)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.health_status} />
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
                        <Label htmlFor="target_end_date">Target end date</Label>
                        <Input
                            id="target_end_date"
                            type="date"
                            value={form.data.target_end_date}
                            onChange={(event) =>
                                form.setData(
                                    'target_end_date',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.target_end_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="budget_amount">Budget amount</Label>
                        <Input
                            id="budget_amount"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.budget_amount}
                            onChange={(event) =>
                                form.setData(
                                    'budget_amount',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.budget_amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="budget_hours">Budget hours</Label>
                        <Input
                            id="budget_hours"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.budget_hours}
                            onChange={(event) =>
                                form.setData(
                                    'budget_hours',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.budget_hours} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="progress_percent">Initial progress %</Label>
                        <Input
                            id="progress_percent"
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            value={form.data.progress_percent}
                            onChange={(event) =>
                                form.setData(
                                    'progress_percent',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.progress_percent} />
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
                        Create project
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
