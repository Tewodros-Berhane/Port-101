import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import PurchasingLineItemsEditor, {
    type PurchasingLineItemInput,
} from '@/components/purchasing/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type Props = {
    order: {
        rfq_id: string;
        partner_id: string;
        order_date: string;
        notes: string;
        lines: PurchasingLineItemInput[];
    };
    partners: Partner[];
    products: Product[];
    rfqs: Rfq[];
};

export default function PurchaseOrderCreate({
    order,
    partners,
    products,
    rfqs,
}: Props) {
    const form = useForm({
        rfq_id: order.rfq_id,
        partner_id: order.partner_id,
        order_date: order.order_date,
        notes: order.notes,
        lines: order.lines,
    });

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.purchasing, { title: 'Purchase Orders', href: '/company/purchasing/orders' },
                {
                    title: 'Create',
                    href: '/company/purchasing/orders/create',
                },)}
        >
            <Head title="New Purchase Order" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New purchase order</h1>
                    <p className="text-sm text-muted-foreground">
                        Create a PO directly or from an RFQ.
                    </p>
                </div>
                <BackLinkAction href="/company/purchasing/orders" label="Back to purchase orders" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/purchasing/orders');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="rfq_id">Linked RFQ</Label>
                        <select
                            id="rfq_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.rfq_id}
                            onChange={(event) => {
                                const rfqId = event.target.value;
                                form.setData('rfq_id', rfqId);

                                if (!rfqId) {
                                    return;
                                }

                                const selectedRfq = rfqs.find(
                                    (item) => item.id === rfqId,
                                );

                                if (selectedRfq) {
                                    form.setData(
                                        'partner_id',
                                        selectedRfq.partner_id,
                                    );
                                }
                            }}
                        >
                            <option value="">No linked RFQ</option>
                            {rfqs.map((rfq) => (
                                <option key={rfq.id} value={rfq.id}>
                                    {rfq.rfq_number}
                                    {rfq.partner_name
                                        ? ` - ${rfq.partner_name}`
                                        : ''}
                                    {` (${rfq.status.replace('_', ' ')})`}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.rfq_id} />
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
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <PurchasingLineItemsEditor
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
                        Create purchase order
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function calculateTotals(lines: PurchasingLineItemInput[]) {
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
