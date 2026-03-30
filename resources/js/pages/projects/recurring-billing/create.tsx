import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type ProjectOption = {
    id: string;
    project_code: string;
    name: string;
    customer_id?: string | null;
    customer_name?: string | null;
    currency_id?: string | null;
};

type CustomerOption = {
    id: string;
    name: string;
};

type CurrencyOption = {
    id: string;
    code: string;
    name: string;
};

type Props = {
    recurringBilling: {
        project_id: string;
        customer_id: string;
        currency_id: string;
        name: string;
        description: string;
        frequency: string;
        quantity: string;
        unit_price: string;
        invoice_due_days: string;
        starts_on: string;
        next_run_on: string;
        ends_on: string;
        auto_create_invoice_draft: boolean;
        invoice_grouping: string;
        status: string;
    };
    projects: ProjectOption[];
    customers: CustomerOption[];
    currencies: CurrencyOption[];
    frequencies: string[];
    statuses: string[];
    invoiceGroupingOptions: string[];
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectRecurringBillingCreate({
    recurringBilling,
    projects,
    customers,
    currencies,
    frequencies,
    statuses,
    invoiceGroupingOptions,
}: Props) {
    const form = useForm({
        project_id: recurringBilling.project_id,
        customer_id: recurringBilling.customer_id,
        currency_id: recurringBilling.currency_id,
        name: recurringBilling.name,
        description: recurringBilling.description,
        frequency: recurringBilling.frequency,
        quantity: recurringBilling.quantity,
        unit_price: recurringBilling.unit_price,
        invoice_due_days: recurringBilling.invoice_due_days,
        starts_on: recurringBilling.starts_on,
        next_run_on: recurringBilling.next_run_on,
        ends_on: recurringBilling.ends_on,
        auto_create_invoice_draft: recurringBilling.auto_create_invoice_draft,
        invoice_grouping: recurringBilling.invoice_grouping,
        status: recurringBilling.status,
    });

    const selectedProject = projects.find(
        (project) => project.id === form.data.project_id,
    );
    const recurringAmount =
        (Number(form.data.quantity || 0) * Number(form.data.unit_price || 0)) ||
        0;

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.projects, {
                    title: 'Recurring Billing',
                    href: '/company/projects/recurring-billing',
                },
                {
                    title: 'Create',
                    href: '/company/projects/recurring-billing/create',
                },)}
        >
            <Head title="New Recurring Billing Schedule" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        New recurring billing schedule
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Set the project retainer cadence, commercial amount, and
                        whether due cycles should auto-create draft invoices.
                    </p>
                </div>
                <BackLinkAction href="/company/projects/recurring-billing" label="Back to recurring billing" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        auto_create_invoice_draft: data.auto_create_invoice_draft
                            ? 1
                            : 0,
                    })).post('/company/projects/recurring-billing');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="project_id">Project</Label>
                        <select
                            id="project_id"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.project_id}
                            onChange={(event) => {
                                const nextProject = projects.find(
                                    (project) =>
                                        project.id === event.target.value,
                                );

                                form.setData((current) => ({
                                    ...current,
                                    project_id: event.target.value,
                                    customer_id:
                                        current.customer_id ||
                                        nextProject?.customer_id ||
                                        '',
                                    currency_id:
                                        current.currency_id ||
                                        nextProject?.currency_id ||
                                        '',
                                }));
                            }}
                        >
                            <option value="">Select project</option>
                            {projects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.project_code} - {project.name}
                                    {project.customer_name
                                        ? ` (${project.customer_name})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.project_id} />
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

                    <div className="grid gap-2 md:col-span-2 xl:col-span-3">
                        <Label htmlFor="name">Schedule name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Managed services retainer"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2 md:col-span-2 xl:col-span-4">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder="Commercial description used on generated billables and invoice draft lines."
                        />
                        <InputError message={form.errors.description} />
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
                            <option value="">Select customer</option>
                            {customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.customer_id} />
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
                            <option value="">Select currency</option>
                            {currencies.map((currency) => (
                                <option key={currency.id} value={currency.id}>
                                    {currency.code} - {currency.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.currency_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="frequency">Frequency</Label>
                        <select
                            id="frequency"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.frequency}
                            onChange={(event) =>
                                form.setData('frequency', event.target.value)
                            }
                        >
                            {frequencies.map((frequency) => (
                                <option key={frequency} value={frequency}>
                                    {formatLabel(frequency)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.frequency} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="invoice_grouping">Invoice grouping</Label>
                        <select
                            id="invoice_grouping"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.invoice_grouping}
                            onChange={(event) =>
                                form.setData(
                                    'invoice_grouping',
                                    event.target.value,
                                )
                            }
                        >
                            {invoiceGroupingOptions.map((option) => (
                                <option key={option} value={option}>
                                    {formatLabel(option)}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.invoice_grouping} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quantity">Quantity</Label>
                        <Input
                            id="quantity"
                            type="number"
                            min={0.0001}
                            step="0.0001"
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData('quantity', event.target.value)
                            }
                        />
                        <InputError message={form.errors.quantity} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="unit_price">Unit price</Label>
                        <Input
                            id="unit_price"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.unit_price}
                            onChange={(event) =>
                                form.setData('unit_price', event.target.value)
                            }
                        />
                        <InputError message={form.errors.unit_price} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="invoice_due_days">Invoice due days</Label>
                        <Input
                            id="invoice_due_days"
                            type="number"
                            min={0}
                            step="1"
                            value={form.data.invoice_due_days}
                            onChange={(event) =>
                                form.setData(
                                    'invoice_due_days',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.invoice_due_days} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="starts_on">Starts on</Label>
                        <Input
                            id="starts_on"
                            type="date"
                            value={form.data.starts_on}
                            onChange={(event) =>
                                form.setData('starts_on', event.target.value)
                            }
                        />
                        <InputError message={form.errors.starts_on} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="next_run_on">Next run on</Label>
                        <Input
                            id="next_run_on"
                            type="date"
                            value={form.data.next_run_on}
                            onChange={(event) =>
                                form.setData('next_run_on', event.target.value)
                            }
                        />
                        <InputError message={form.errors.next_run_on} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ends_on">Ends on</Label>
                        <Input
                            id="ends_on"
                            type="date"
                            value={form.data.ends_on}
                            onChange={(event) =>
                                form.setData('ends_on', event.target.value)
                            }
                        />
                        <InputError message={form.errors.ends_on} />
                    </div>
                </div>

                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-[1.2fr_0.8fr]">
                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold">
                            Automation and handoff
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Auto-invoice only generates draft invoices when the
                            resulting billable does not require approval. If
                            approval thresholds apply, the schedule run stays in
                            the Projects billing queue until approved.
                        </p>
                        {selectedProject && (
                            <p className="text-xs text-muted-foreground">
                                Selected project: {selectedProject.project_code} -{' '}
                                {selectedProject.name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-3 rounded-xl border border-dashed p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium">
                                    Auto-create draft invoice
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Uses the existing Projects to Accounting
                                    draft handoff.
                                </p>
                            </div>
                            <input
                                type="checkbox"
                                className="size-4 rounded border-input"
                                checked={form.data.auto_create_invoice_draft}
                                onChange={(event) =>
                                    form.setData(
                                        'auto_create_invoice_draft',
                                        event.target.checked,
                                    )
                                }
                            />
                        </div>
                        <div className="rounded-lg bg-muted/40 px-3 py-3 text-sm">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Recurring amount
                            </p>
                            <p className="mt-2 text-2xl font-semibold">
                                {recurringAmount.toFixed(2)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {form.data.quantity || '0'} x {form.data.unit_price || '0.00'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Create schedule
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
