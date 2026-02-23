import AccountingInvoiceLineItemsEditor, {
    type AccountingInvoiceLineInput,
} from '@/components/accounting/invoice-line-items-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
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

type PaymentPreview = {
    id: string;
    payment_number: string;
    status: string;
    amount: number;
    payment_date?: string | null;
};

type Invoice = {
    id: string;
    partner_id: string;
    partner_name?: string | null;
    document_type: string;
    sales_order_id?: string | null;
    sales_order_number?: string | null;
    invoice_number: string;
    status: string;
    delivery_status: string;
    invoice_date: string;
    due_date?: string | null;
    subtotal: number;
    tax_total: number;
    grand_total: number;
    paid_total: number;
    balance_due: number;
    posted_at?: string | null;
    cancelled_at?: string | null;
    notes?: string | null;
    lines: AccountingInvoiceLineInput[];
    recent_payments: PaymentPreview[];
};

type Props = {
    invoice: Invoice;
    documentTypes: string[];
    partners: Partner[];
    products: Product[];
    salesOrders: SalesOrder[];
};

export default function AccountingInvoiceEdit({
    invoice,
    documentTypes,
    partners,
    products,
    salesOrders,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('accounting.invoices.manage');
    const canPost = hasPermission('accounting.invoices.post');
    const isDraft = invoice.status === 'draft';

    const form = useForm({
        partner_id: invoice.partner_id,
        document_type: invoice.document_type,
        invoice_date: invoice.invoice_date,
        due_date: invoice.due_date ?? '',
        notes: invoice.notes ?? '',
        lines: invoice.lines,
    });
    const actionForm = useForm({});

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Accounting', href: '/company/accounting' },
                { title: 'Invoices', href: '/company/accounting/invoices' },
                {
                    title: invoice.invoice_number,
                    href: `/company/accounting/invoices/${invoice.id}/edit`,
                },
            ]}
        >
            <Head title={invoice.invoice_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit invoice</h1>
                    <p className="text-sm text-muted-foreground">
                        {invoice.invoice_number} -{' '}
                        {invoice.status.replace('_', ' ')}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={`/company/accounting/payments/create?invoice_id=${invoice.id}`}>
                            Register payment
                        </Link>
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href="/company/accounting/invoices">Back</Link>
                    </Button>
                </div>
            </div>

            {invoice.delivery_status === 'pending_delivery' && (
                <div className="mt-6 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    Posting is blocked until delivery is marked ready.
                </div>
            )}

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/accounting/invoices/${invoice.id}`);
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
                            disabled={!isDraft || form.processing}
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
                            value={invoice.sales_order_id ?? ''}
                            disabled
                        >
                            <option value="">No linked sales order</option>
                            {salesOrders.map((order) => (
                                <option key={order.id} value={order.id}>
                                    {order.order_number}
                                </option>
                            ))}
                        </select>
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
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <AccountingInvoiceLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={!isDraft || form.processing}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-5">
                    <div>
                        <p className="text-xs text-muted-foreground">Subtotal</p>
                        <p className="font-semibold">{totals.subtotal}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Tax</p>
                        <p className="font-semibold">{totals.taxTotal}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Total</p>
                        <p className="font-semibold">{totals.grandTotal}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Paid</p>
                        <p className="font-semibold">
                            {invoice.paid_total.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Balance</p>
                        <p className="font-semibold">
                            {invoice.balance_due.toFixed(2)}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && isDraft && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canPost && isDraft && (
                        <Button
                            type="button"
                            onClick={() =>
                                actionForm.post(
                                    `/company/accounting/invoices/${invoice.id}/post`,
                                )
                            }
                            disabled={actionForm.processing}
                        >
                            Post invoice
                        </Button>
                    )}

                    {canPost &&
                        (invoice.status === 'draft' ||
                            invoice.status === 'posted') && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    actionForm.post(
                                        `/company/accounting/invoices/${invoice.id}/cancel`,
                                    )
                                }
                                disabled={actionForm.processing}
                            >
                                Cancel invoice
                            </Button>
                        )}

                    {canManage && isDraft && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(
                                    `/company/accounting/invoices/${invoice.id}`,
                                )
                            }
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    )}
                </div>
            </form>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold">Recent payments</h2>
                    <Button variant="ghost" asChild>
                        <Link href="/company/accounting/payments">Open</Link>
                    </Button>
                </div>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[620px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Payment
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Date
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {invoice.recent_payments.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No payments linked yet.
                                    </td>
                                </tr>
                            )}
                            {invoice.recent_payments.map((payment) => (
                                <tr key={payment.id}>
                                    <td className="px-3 py-2">
                                        <Link
                                            href={`/company/accounting/payments/${payment.id}/edit`}
                                            className="font-medium text-primary"
                                        >
                                            {payment.payment_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {payment.status}
                                    </td>
                                    <td className="px-3 py-2">
                                        {payment.payment_date ?? '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {payment.amount.toFixed(2)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
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
