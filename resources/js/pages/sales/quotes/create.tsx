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
    title?: string;
};

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
};

type Props = {
    quote: {
        lead_id?: string | null;
        partner_id?: string | null;
        quote_date: string;
        valid_until?: string | null;
        lines: SalesLineItem[];
    };
    leads: Option[];
    partners: Option[];
    products: ProductOption[];
};

export default function SalesQuoteCreate({
    quote,
    leads,
    partners,
    products,
}: Props) {
    const form = useForm({
        lead_id: quote.lead_id ?? '',
        partner_id: quote.partner_id ?? '',
        quote_date: quote.quote_date,
        valid_until: quote.valid_until ?? '',
        lines: quote.lines,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.sales, { title: 'Quotes', href: '/company/sales/quotes' },
                { title: 'Create', href: '/company/sales/quotes/create' },)}
        >
            <Head title="New Quote" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New quote</h1>
                    <p className="text-sm text-muted-foreground">
                        Build quote lines and trigger approval based on policy.
                    </p>
                </div>
                <BackLinkAction href="/company/sales/quotes" label="Back to quotes" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/sales/quotes');
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
                        />
                        <InputError message={form.errors.valid_until} />
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
                        Create quote
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
