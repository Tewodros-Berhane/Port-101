import SalesLineItemsEditor, {
    type SalesLineItem,
} from '@/components/sales/line-items-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Option = {
    id: string;
    name?: string;
    title?: string;
};

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
};

type Quote = {
    id: string;
    lead_id?: string | null;
    partner_id?: string | null;
    quote_number: string;
    status: string;
    quote_date: string;
    valid_until?: string | null;
    subtotal: number;
    discount_total: number;
    tax_total: number;
    grand_total: number;
    requires_approval: boolean;
    approved_at?: string | null;
    order_id?: string | null;
    lines: SalesLineItem[];
};

type Props = {
    quote: Quote;
    leads: Option[];
    partners: Option[];
    products: ProductOption[];
};

export default function SalesQuoteEdit({
    quote,
    leads,
    partners,
    products,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('sales.quotes.manage');
    const canApprove = hasPermission('sales.quotes.approve');

    const form = useForm({
        lead_id: quote.lead_id ?? '',
        partner_id: quote.partner_id ?? '',
        quote_date: quote.quote_date,
        valid_until: quote.valid_until ?? '',
        lines: quote.lines,
    });

    const actionForm = useForm({
        reason: '',
    });

    const isConfirmed = quote.status === 'confirmed';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Sales', href: '/company/sales' },
                { title: 'Quotes', href: '/company/sales/quotes' },
                {
                    title: quote.quote_number,
                    href: `/company/sales/quotes/${quote.id}/edit`,
                },
            ]}
        >
            <Head title={quote.quote_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit quote</h1>
                    <p className="text-sm text-muted-foreground">
                        {quote.quote_number} - {quote.status}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="ghost" asChild>
                        <Link href="/company/sales/quotes">Back</Link>
                    </Button>
                    {quote.order_id && (
                        <Button variant="outline" asChild>
                            <Link href={`/company/sales/orders/${quote.order_id}/edit`}>
                                Open order
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {quote.requires_approval && !quote.approved_at && (
                <div className="mt-6 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    This quote requires manager approval before confirmation.
                </div>
            )}

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/sales/quotes/${quote.id}`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="lead_id">Lead</Label>
                        <select
                            id="lead_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.lead_id}
                            onChange={(event) =>
                                form.setData('lead_id', event.target.value)
                            }
                            disabled={form.processing || isConfirmed}
                        >
                            <option value="">No linked lead</option>
                            {leads.map((lead) => (
                                <option key={lead.id} value={lead.id}>
                                    {lead.title}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.lead_id} />
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
                            disabled={form.processing || isConfirmed}
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
                        <Label htmlFor="quote_date">Quote date</Label>
                        <Input
                            id="quote_date"
                            type="date"
                            value={form.data.quote_date}
                            onChange={(event) =>
                                form.setData('quote_date', event.target.value)
                            }
                            required
                            disabled={form.processing || isConfirmed}
                        />
                        <InputError message={form.errors.quote_date} />
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
                            disabled={form.processing || isConfirmed}
                        />
                        <InputError message={form.errors.valid_until} />
                    </div>
                </div>

                <SalesLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={form.processing || isConfirmed}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-4">
                    <div>
                        <p className="text-xs text-muted-foreground">Subtotal</p>
                        <p className="font-semibold">
                            {quote.subtotal.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Discount</p>
                        <p className="font-semibold">
                            {quote.discount_total.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Tax</p>
                        <p className="font-semibold">
                            {quote.tax_total.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Total</p>
                        <p className="font-semibold">
                            {quote.grand_total.toFixed(2)}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && !isConfirmed && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canManage && !isConfirmed && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/send`,
                                )
                            }
                        >
                            Mark sent
                        </Button>
                    )}

                    {canApprove && !isConfirmed && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/approve`,
                                )
                            }
                        >
                            Approve
                        </Button>
                    )}

                    {canApprove && !isConfirmed && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() => {
                                const reason = window.prompt(
                                    'Rejection reason (optional):',
                                    '',
                                );

                                actionForm.setData('reason', reason ?? '');
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/reject`,
                                );
                            }}
                        >
                            Reject
                        </Button>
                    )}

                    {canManage && !isConfirmed && (
                        <Button
                            type="button"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/confirm`,
                                )
                            }
                        >
                            Confirm quote
                        </Button>
                    )}

                    {canManage && !isConfirmed && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() =>
                                form.delete(`/company/sales/quotes/${quote.id}`)
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
