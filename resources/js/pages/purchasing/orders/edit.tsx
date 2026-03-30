import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import PurchasingLineItemsEditor, {
    type PurchasingLineItemInput,
} from '@/components/purchasing/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

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

type Rfq = {
    id: string;
    rfq_number: string;
    partner_id: string;
    partner_name?: string | null;
    status: string;
};

type OrderLine = PurchasingLineItemInput & {
    id: string;
    received_quantity: number;
};

type VendorBill = {
    id: string;
    invoice_number: string;
    status: string;
    invoice_date?: string | null;
    balance_due: number;
};

type Order = {
    id: string;
    rfq_id?: string | null;
    rfq_number?: string | null;
    partner_id: string;
    partner_name?: string | null;
    order_number: string;
    status: string;
    order_date: string;
    subtotal: number;
    tax_total: number;
    grand_total: number;
    requires_approval: boolean;
    approved_at?: string | null;
    ordered_at?: string | null;
    received_at?: string | null;
    billed_at?: string | null;
    notes?: string | null;
    lines: OrderLine[];
    vendor_bills: VendorBill[];
};

type Props = {
    order: Order;
    partners: Partner[];
    products: Product[];
    rfqs: Rfq[];
};

export default function PurchaseOrderEdit({
    order,
    partners,
    products,
    rfqs,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('purchasing.po.manage');
    const canApprove = hasPermission('purchasing.po.approve');
    const isDraft = order.status === 'draft';

    const form = useForm({
        partner_id: order.partner_id,
        order_date: order.order_date,
        notes: order.notes ?? '',
        lines: order.lines.map((line) => ({
            product_id: line.product_id,
            description: line.description,
            quantity: line.quantity,
            unit_cost: line.unit_cost,
            tax_rate: line.tax_rate,
        })),
    });
    const actionForm = useForm({});

    const receiveForm = useForm<{
        lines: Array<{ line_id: string; quantity: number }>;
    }>({
        lines: order.lines
            .map((line) => ({
                line_id: line.id,
                quantity: Math.max(line.quantity - line.received_quantity, 0),
            }))
            .filter((line) => line.quantity > 0),
    });

    const canPlace =
        canManage && (order.status === 'draft' || order.status === 'approved');
    const canReceive =
        canManage &&
        (order.status === 'ordered' || order.status === 'partially_received');

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.purchasing, { title: 'Purchase Orders', href: '/company/purchasing/orders' },
                {
                    title: order.order_number,
                    href: `/company/purchasing/orders/${order.id}/edit`,
                },)}
        >
            <Head title={order.order_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit purchase order</h1>
                    <p className="text-sm text-muted-foreground">
                        {order.order_number} - {order.status.replace('_', ' ')}
                    </p>
                </div>
                <BackLinkAction href="/company/purchasing/orders" label="Back to purchase orders" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/purchasing/orders/${order.id}`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="rfq_id">Linked RFQ</Label>
                        <select
                            id="rfq_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={order.rfq_id ?? ''}
                            disabled
                        >
                            <option value="">No linked RFQ</option>
                            {rfqs.map((rfq) => (
                                <option key={rfq.id} value={rfq.id}>
                                    {rfq.rfq_number}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="partner_id">Vendor</Label>
                        <select
                            id="partner_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.partner_id}
                            onChange={(event) =>
                                form.setData('partner_id', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        >
                            <option value="">Select vendor</option>
                            {partners.map((partner) => (
                                <option key={partner.id} value={partner.id}>
                                    {partner.name} ({partner.type})
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.partner_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="order_date">Order date</Label>
                        <Input
                            id="order_date"
                            type="date"
                            value={form.data.order_date}
                            onChange={(event) =>
                                form.setData('order_date', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.order_date} />
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

                <PurchasingLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={!isDraft || form.processing}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-6">
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
                        <p className="text-xs text-muted-foreground">Approval</p>
                        <p className="font-semibold">
                            {order.requires_approval ? 'Required' : 'Not required'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Ordered at</p>
                        <p className="font-semibold">{order.ordered_at ?? '-'}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Received at</p>
                        <p className="font-semibold">{order.received_at ?? '-'}</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && isDraft && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canApprove && order.status === 'draft' && (
                        <Button
                            type="button"
                            onClick={() =>
                                actionForm.post(
                                    `/company/purchasing/orders/${order.id}/approve`,
                                )
                            }
                            disabled={actionForm.processing}
                        >
                            Approve order
                        </Button>
                    )}

                    {canPlace && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                actionForm.post(
                                    `/company/purchasing/orders/${order.id}/place`,
                                )
                            }
                            disabled={actionForm.processing}
                        >
                            Place order
                        </Button>
                    )}

                    {canManage && isDraft && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(
                                    `/company/purchasing/orders/${order.id}`,
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
                <h2 className="text-sm font-semibold">Receipt progress</h2>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Description
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Ordered
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Received
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Outstanding
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {order.lines.map((line) => {
                                const outstanding = Math.max(
                                    line.quantity - line.received_quantity,
                                    0,
                                );

                                return (
                                    <tr key={line.id}>
                                        <td className="px-3 py-2">
                                            {line.description}
                                        </td>
                                        <td className="px-3 py-2">
                                            {line.quantity.toFixed(4)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {line.received_quantity.toFixed(4)}
                                        </td>
                                        <td className="px-3 py-2 font-medium">
                                            {outstanding.toFixed(4)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {canReceive && receiveForm.data.lines.length > 0 && (
                    <div className="mt-4 rounded-lg border p-4">
                        <h3 className="text-sm font-semibold">Record receipt</h3>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Quantities default to current outstanding amounts.
                        </p>

                        <div className="mt-3 grid gap-3">
                            {receiveForm.data.lines.map((line, index) => {
                                const orderLine = order.lines.find(
                                    (item) => item.id === line.line_id,
                                );

                                return (
                                    <div
                                        key={line.line_id}
                                        className="grid gap-2 md:grid-cols-[1fr_220px]"
                                    >
                                        <div className="rounded-md border px-3 py-2 text-sm">
                                            {orderLine?.description ??
                                                line.line_id}
                                        </div>
                                        <div className="grid gap-1">
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.0001"
                                                value={line.quantity}
                                                onChange={(event) => {
                                                    const next = [
                                                        ...receiveForm.data
                                                            .lines,
                                                    ];
                                                    next[index] = {
                                                        ...next[index],
                                                        quantity:
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ) || 0,
                                                    };
                                                    receiveForm.setData(
                                                        'lines',
                                                        next,
                                                    );
                                                }}
                                            />
                                            <InputError
                                                message={
                                                    receiveForm.errors[
                                                        `lines.${index}.quantity`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <InputError message={receiveForm.errors.lines} />

                        <div className="mt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    receiveForm.post(
                                        `/company/purchasing/orders/${order.id}/receive`,
                                    )
                                }
                                disabled={receiveForm.processing}
                            >
                                Record receipt
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold">Vendor bills</h2>
                    <Button variant="ghost" asChild>
                        <Link href="/company/accounting/invoices">
                            Open accounting
                        </Link>
                    </Button>
                </div>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[620px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Bill #
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Date
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {order.vendor_bills.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No vendor bills linked yet.
                                    </td>
                                </tr>
                            )}
                            {order.vendor_bills.map((bill) => (
                                <tr key={bill.id}>
                                    <td className="px-3 py-2">
                                        <Link
                                            href={`/company/accounting/invoices/${bill.id}/edit`}
                                            className="font-medium text-primary"
                                        >
                                            {bill.invoice_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {bill.status.replace('_', ' ')}
                                    </td>
                                    <td className="px-3 py-2">
                                        {bill.invoice_date ?? '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {bill.balance_due.toFixed(2)}
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

function calculateTotals(
    lines: Array<{ quantity: number; unit_cost: number; tax_rate: number }>,
) {
    const values = lines.reduce(
        (accumulator, line) => {
            const quantity = Number(line.quantity) || 0;
            const unitCost = Number(line.unit_cost) || 0;
            const taxRate = Number(line.tax_rate) || 0;
            const lineSubtotal = quantity * unitCost;
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
