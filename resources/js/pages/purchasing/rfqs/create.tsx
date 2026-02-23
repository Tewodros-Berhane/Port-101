import PurchasingLineItemsEditor, {
    type PurchasingLineItemInput,
} from '@/components/purchasing/line-items-editor';
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

type Props = {
    rfq: {
        partner_id: string;
        rfq_date: string;
        valid_until: string;
        notes: string;
        lines: PurchasingLineItemInput[];
    };
    partners: Partner[];
    products: Product[];
};

export default function PurchaseRfqCreate({ rfq, partners, products }: Props) {
    const form = useForm({
        partner_id: rfq.partner_id,
        rfq_date: rfq.rfq_date,
        valid_until: rfq.valid_until,
        notes: rfq.notes,
        lines: rfq.lines,
    });

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Purchasing', href: '/company/purchasing' },
                { title: 'RFQs', href: '/company/purchasing/rfqs' },
                {
                    title: 'Create',
                    href: '/company/purchasing/rfqs/create',
                },
            ]}
        >
            <Head title="New Purchase RFQ" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New RFQ</h1>
                    <p className="text-sm text-muted-foreground">
                        Draft a vendor request for quotation.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/purchasing/rfqs">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/purchasing/rfqs');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3">
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
                        <Label htmlFor="rfq_date">RFQ date</Label>
                        <Input
                            id="rfq_date"
                            type="date"
                            value={form.data.rfq_date}
                            onChange={(event) =>
                                form.setData('rfq_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.rfq_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="valid_until">Valid until</Label>
                        <Input
                            id="valid_until"
                            type="date"
                            value={form.data.valid_until}
                            onChange={(event) =>
                                form.setData('valid_until', event.target.value)
                            }
                        />
                        <InputError message={form.errors.valid_until} />
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
                        Create RFQ
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
