import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import SalesLineItemsEditor, {
    type SalesLineItem,
} from '@/components/sales/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Option = {
    id: string;
    name?: string;
    quote_number?: string;
    partner_id?: string;
};

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
};

type Order = {
    id: string;
    quote_id?: string | null;
    partner_id?: string | null;
    order_number: string;
    status: string;
    order_date: string;
    subtotal: number;
    discount_total: number;
    tax_total: number;
    grand_total: number;
    requires_approval: boolean;
    approved_at?: string | null;
    confirmed_at?: string | null;
    lines: SalesLineItem[];
};

type Props = {
    order: Order;
    quotes: Option[];
    partners: Option[];
    products: ProductOption[];
};

export default function SalesOrderEdit({
    order,
    quotes,
    partners,
    products,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('sales.orders.manage');
    const canApprove = hasPermission('sales.orders.approve');

    const form = useForm({
        partner_id: order.partner_id ?? '',
        order_date: order.order_date,
        lines: order.lines,
    });

    const actionForm = useForm({});
    const isDraft = order.status === 'draft';

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.sales, { title: 'Orders', href: '/company/sales/orders' },
                {
                    title: order.order_number,
                    href: `/company/sales/orders/${order.id}/edit`,
                },)}
        >
            <Head title={order.order_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit order</h1>
                    <p className="text-sm text-muted-foreground">
                        {order.order_number} - {order.status}
                    </p>
                </div>
                <BackLinkAction href="/company/sales/orders" label="Back to orders" variant="ghost" />
            </div>

            {order.requires_approval && !order.approved_at && (
                <div className="mt-6 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    This order requires manager approval before confirmation.
                </div>
            )}

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/sales/orders/${order.id}`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="linked_quote">Linked quote</Label>
                        <select
                            id="linked_quote"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={order.quote_id ?? ''}
                            disabled
                        >
                            <option value="">No linked quote</option>
                            {quotes.map((quote) => (
                                <option key={quote.id} value={quote.id}>
                                    {quote.quote_number}
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
                            required
                            disabled={form.processing || !isDraft}
                        >
                            <option value="">Select partner</option>
                            {partners.map((partner) => (
                                <option key={partner.id} value={partner.id}>
                                    {partner.name}
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
                            required
                            disabled={form.processing || !isDraft}
                        />
                        <InputError message={form.errors.order_date} />
                    </div>
                </div>

                <SalesLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={form.processing || !isDraft}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-4">
                    <div>
                        <p className="text-xs text-muted-foreground">Subtotal</p>
                        <p className="font-semibold">
                            {order.subtotal.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Discount</p>
                        <p className="font-semibold">
                            {order.discount_total.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Tax</p>
                        <p className="font-semibold">{order.tax_total.toFixed(2)}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Total</p>
                        <p className="font-semibold">
                            {order.grand_total.toFixed(2)}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && isDraft && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canApprove && isDraft && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/orders/${order.id}/approve`,
                                )
                            }
                        >
                            Approve
                        </Button>
                    )}

                    {canManage && isDraft && (
                        <Button
                            type="button"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/orders/${order.id}/confirm`,
                                )
                            }
                        >
                            Confirm order
                        </Button>
                    )}

                    {canManage && isDraft && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(`/company/sales/orders/${order.id}`)
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
