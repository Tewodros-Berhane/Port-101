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
    partner_id: string;
    partner_name?: string | null;
    rfq_number: string;
    status: string;
    rfq_date: string;
    valid_until?: string | null;
    subtotal: number;
    tax_total: number;
    grand_total: number;
    sent_at?: string | null;
    vendor_responded_at?: string | null;
    selected_at?: string | null;
    notes?: string | null;
    order_id?: string | null;
    order_number?: string | null;
    order_status?: string | null;
    lines: PurchasingLineItemInput[];
};

type Props = {
    rfq: Rfq;
    partners: Partner[];
    products: Product[];
};

export default function PurchaseRfqEdit({ rfq, partners, products }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('purchasing.rfq.manage');
    const isDraft = rfq.status === 'draft';

    const form = useForm({
        partner_id: rfq.partner_id,
        rfq_date: rfq.rfq_date,
        valid_until: rfq.valid_until ?? '',
        notes: rfq.notes ?? '',
        lines: rfq.lines,
    });
    const actionForm = useForm({});

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.purchasing, { title: 'RFQs', href: '/company/purchasing/rfqs' },
                {
                    title: rfq.rfq_number,
                    href: `/company/purchasing/rfqs/${rfq.id}/edit`,
                },)}
        >
            <Head title={rfq.rfq_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit RFQ</h1>
                    <p className="text-sm text-muted-foreground">
                        {rfq.rfq_number} - {rfq.status.replace('_', ' ')}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {rfq.order_id && (
                        <Button variant="outline" asChild>
                            <Link href={`/company/purchasing/orders/${rfq.order_id}/edit`}>
                                Open linked PO
                            </Link>
                        </Button>
                    )}
                    <BackLinkAction href="/company/purchasing/rfqs" label="Back to RFQs" variant="ghost" />
                </div>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/purchasing/rfqs/${rfq.id}`);
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
                        <Label htmlFor="rfq_date">RFQ date</Label>
                        <Input
                            id="rfq_date"
                            type="date"
                            value={form.data.rfq_date}
                            onChange={(event) =>
                                form.setData('rfq_date', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
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
                        <p className="text-xs text-muted-foreground">Sent at</p>
                        <p className="font-semibold">{rfq.sent_at ?? '-'}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Selected at</p>
                        <p className="font-semibold">{rfq.selected_at ?? '-'}</p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && isDraft && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canManage && rfq.status === 'draft' && (
                        <Button
                            type="button"
                            onClick={() =>
                                actionForm.post(
                                    `/company/purchasing/rfqs/${rfq.id}/send`,
                                )
                            }
                            disabled={actionForm.processing}
                        >
                            Mark sent
                        </Button>
                    )}

                    {canManage &&
                        (rfq.status === 'draft' || rfq.status === 'sent') && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    actionForm.post(
                                        `/company/purchasing/rfqs/${rfq.id}/respond`,
                                    )
                                }
                                disabled={actionForm.processing}
                            >
                                Mark vendor responded
                            </Button>
                        )}

                    {canManage &&
                        (rfq.status === 'sent' ||
                            rfq.status === 'vendor_responded') && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    actionForm.post(
                                        `/company/purchasing/rfqs/${rfq.id}/select`,
                                    )
                                }
                                disabled={actionForm.processing}
                            >
                                Select and create PO
                            </Button>
                        )}

                    {canManage && isDraft && !rfq.order_id && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(`/company/purchasing/rfqs/${rfq.id}`)
                            }
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    )}
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
