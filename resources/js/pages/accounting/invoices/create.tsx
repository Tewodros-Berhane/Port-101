import AccountingInvoiceLineItemsEditor, {
    type AccountingInvoiceLineInput,
} from '@/components/accounting/invoice-line-items-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Partner = {
    id: string;
    name: string;
    type: string;
};

type Product = {
    id: string;
    name: string;
    sku?: string | null;
};

type SalesOrder = {
    id: string;
    order_number: string;
    partner_id: string;
};

type Props = {
    invoice: {
        partner_id: string;
        document_type: string;
        sales_order_id: string;
        invoice_date: string;
        due_date: string;
        notes: string;
        lines: AccountingInvoiceLineInput[];
    };
    documentTypes: string[];
    partners: Partner[];
    products: Product[];
    salesOrders: SalesOrder[];
};

export default function AccountingInvoiceCreate({
    invoice,
    documentTypes,
    partners,
    products,
    salesOrders,
}: Props) {
    const form = useForm({
        partner_id: invoice.partner_id,
        document_type: invoice.document_type,
        sales_order_id: invoice.sales_order_id,
        invoice_date: invoice.invoice_date,
        due_date: invoice.due_date,
        notes: invoice.notes,
        lines: invoice.lines,
    });

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Accounting', href: '/company/accounting' },
                { title: 'Invoices', href: '/company/accounting/invoices' },
                {
                    title: 'Create',
                    href: '/company/accounting/invoices/create',
                },
            ]}
        >
            <Head title="New Invoice" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New invoice</h1>
                    <p className="text-sm text-muted-foreground">
                        Create customer invoices and vendor bills.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/accounting/invoices">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/accounting/invoices');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="document_type">Document type</Label>
                        <select
                            id="document_type"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.document_type}
                            onChange={(event) =>
                                form.setData(
                                    'document_type',
                                    event.target.value,
                                )
                            }
                        >
                            {documentTypes.map((documentType) => (
                                <option key={documentType} value={documentType}>
                                    {documentType.replace('_', ' ')}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.document_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="sales_order_id">Sales order</Label>
                        <select
                            id="sales_order_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.sales_order_id}
                            onChange={(event) => {
                                const orderId = event.target.value;
                                form.setData('sales_order_id', orderId);

                                if (!orderId) {
                                    return;
                                }

                                const matchedOrder = salesOrders.find(
                                    (order) => order.id === orderId,
                                );

                                if (matchedOrder?.partner_id) {
                                    form.setData(
                                        'partner_id',
                                        matchedOrder.partner_id,
                                    );
                                }
                            }}
                            disabled={
                                form.data.document_type !== 'customer_invoice'
                            }
                        >
                            <option value="">No linked sales order</option>
                            {salesOrders.map((order) => (
                                <option key={order.id} value={order.id}>
                                    {order.order_number}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.sales_order_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="partner_id">Partner</Label>
                        <select
                            id="partner_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.partner_id}
                            onChange={(event) =>
                                form.setData('partner_id', event.target.value)
                            }
                        >
                            <option value="">Select partner</option>
                            {partners.map((partner) => (
                                <option key={partner.id} value={partner.id}>
                                    {partner.name} ({partner.type})
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.partner_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="invoice_date">Invoice date</Label>
                        <Input
                            id="invoice_date"
                            type="date"
                            value={form.data.invoice_date}
                            onChange={(event) =>
                                form.setData('invoice_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.invoice_date} />
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

                    <div className="grid gap-2 xl:col-span-3">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <AccountingInvoiceLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={form.processing}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-3">
                    <div>
                        <p className="text-xs text-muted-foreground">Subtotal</p>
                        <p className="font-semibold">{totals.subtotal}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Tax total</p>
                        <p className="font-semibold">{totals.taxTotal}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Grand total</p>
                        <p className="font-semibold">{totals.grandTotal}</p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Create invoice
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function calculateTotals(lines: AccountingInvoiceLineInput[]) {
    const values = lines.reduce(
        (accumulator, line) => {
            const quantity = Number(line.quantity) || 0;
            const unitPrice = Number(line.unit_price) || 0;
            const taxRate = Number(line.tax_rate) || 0;
            const lineSubtotal = quantity * unitPrice;
            const lineTax = lineSubtotal * (taxRate / 100);

            return {
                subtotal: accumulator.subtotal + lineSubtotal,
                taxTotal: accumulator.taxTotal + lineTax,
                grandTotal: accumulator.grandTotal + lineSubtotal + lineTax,
            };
        },
        { subtotal: 0, taxTotal: 0, grandTotal: 0 },
    );

    return {
        subtotal: values.subtotal.toFixed(2),
        taxTotal: values.taxTotal.toFixed(2),
        grandTotal: values.grandTotal.toFixed(2),
    };
}
