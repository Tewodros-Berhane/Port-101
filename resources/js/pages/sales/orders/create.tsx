import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import SalesLineItemsEditor, {
    type SalesLineItem,
} from '@/components/sales/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type Props = {
    order: {
        quote_id?: string | null;
        partner_id?: string | null;
        order_date: string;
        lines: SalesLineItem[];
    };
    quotes: Option[];
    partners: Option[];
    products: ProductOption[];
};

export default function SalesOrderCreate({
    order,
    quotes,
    partners,
    products,
}: Props) {
    const form = useForm({
        quote_id: order.quote_id ?? '',
        partner_id: order.partner_id ?? '',
        order_date: order.order_date,
        lines: order.lines,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.sales, { title: 'Orders', href: '/company/sales/orders' },
                { title: 'Create', href: '/company/sales/orders/create' },)}
        >
            <Head title="New Sales Order" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New order</h1>
                    <p className="text-sm text-muted-foreground">
                        Build an order directly or from an approved quote.
                    </p>
                </div>
                <BackLinkAction href="/company/sales/orders" label="Back to orders" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/sales/orders');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="quote_id">Quote</Label>
                        <select
                            id="quote_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.quote_id}
                            onChange={(event) => {
                                const nextQuoteId = event.target.value;
                                form.setData('quote_id', nextQuoteId);

                                if (!nextQuoteId) {
                                    return;
                                }

                                const selectedQuote = quotes.find(
                                    (quote) => quote.id === nextQuoteId,
                                );

                                if (selectedQuote?.partner_id) {
                                    form.setData(
                                        'partner_id',
                                        selectedQuote.partner_id,
                                    );
                                }
                            }}
                        >
                            <option value="">No linked quote</option>
                            {quotes.map((quote) => (
                                <option key={quote.id} value={quote.id}>
                                    {quote.quote_number}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.quote_id} />
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
                        />
                        <InputError message={form.errors.order_date} />
                    </div>
                </div>

                <SalesLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={form.processing}
                />

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Create order
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
