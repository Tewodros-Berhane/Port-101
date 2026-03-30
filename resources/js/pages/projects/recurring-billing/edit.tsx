import { Head, Link, useForm } from '@inertiajs/react';
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

type CurrencyOption = {
    id: string;
    code: string;
    name: string;
};

type Props = {
    recurringBilling: {
        id: string;
        project_id: string;
        project_code?: string | null;
        project_name?: string | null;
        customer_id?: string | null;
        currency_id?: string | null;
        name: string;
        description?: string | null;
        frequency: string;
        quantity: string;
        unit_price: string;
        invoice_due_days: string;
        starts_on?: string | null;
        next_run_on?: string | null;
        ends_on?: string | null;
        auto_create_invoice_draft: boolean;
        invoice_grouping: string;
        status: string;
    };
    customers: CustomerOption[];
    currencies: CurrencyOption[];
    frequencies: string[];
    statuses: string[];
    invoiceGroupingOptions: string[];
};

const formatLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function ProjectRecurringBillingEdit({
    recurringBilling,
    customers,
    currencies,
    frequencies,
    statuses,
    invoiceGroupingOptions,
}: Props) {
    const form = useForm({
        customer_id: recurringBilling.customer_id ?? '',
        currency_id: recurringBilling.currency_id ?? '',
        name: recurringBilling.name,
        description: recurringBilling.description ?? '',
        frequency: recurringBilling.frequency,
        quantity: recurringBilling.quantity,
        unit_price: recurringBilling.unit_price,
        invoice_due_days: recurringBilling.invoice_due_days,
        starts_on: recurringBilling.starts_on ?? '',
        next_run_on: recurringBilling.next_run_on ?? '',
        ends_on: recurringBilling.ends_on ?? '',
        auto_create_invoice_draft: recurringBilling.auto_create_invoice_draft,
        invoice_grouping: recurringBilling.invoice_grouping,
        status: recurringBilling.status,
    });

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
                    title: recurringBilling.name,
                    href: `/company/projects/recurring-billing/${recurringBilling.id}/edit`,
                },)}
        >
            <Head title={recurringBilling.name} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Edit recurring billing schedule
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Keep the cadence, billing amount, and automation settings
                        aligned with the current service contract.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={`/company/projects/${recurringBilling.project_id}`}>
                            Open project
                        </Link>
                    </Button>
                    <BackLinkAction href="/company/projects/recurring-billing" label="Back to recurring billing" variant="ghost" />
                </div>
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
                    })).put(
                        `/company/projects/recurring-billing/${recurringBilling.id}`,
                    );
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-lg border border-dashed px-3 py-3 md:col-span-2 xl:col-span-4">
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Linked project
                        </p>
                        <p className="mt-2 text-sm font-medium">
                            {recurringBilling.project_code ?? 'Project'} -{' '}
                            {recurringBilling.project_name ?? '-'}
                        </p>
                    </div>

                    <div className="grid gap-2 md:col-span-2 xl:col-span-3">
                        <Label htmlFor="name">Schedule name</Label>
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

                    <div className="grid gap-2 md:col-span-2 xl:col-span-4">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
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
                            Auto-invoice keeps recurring cycles flowing straight
                            into Accounting as draft invoices when approval is
                            not required. Otherwise the generated billable stays
                            in the billing queue for review.
                        </p>
                    </div>

                    <div className="space-y-3 rounded-xl border border-dashed p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium">
                                    Auto-create draft invoice
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Uses the shared Projects billing handoff.
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
                        Save changes
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
