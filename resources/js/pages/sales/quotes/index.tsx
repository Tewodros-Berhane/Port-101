import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Quote = {
    id: string;
    quote_number: string;
    status: string;
    partner_name?: string | null;
    lead_title?: string | null;
    quote_date?: string | null;
    valid_until?: string | null;
    grand_total: number;
    requires_approval: boolean;
    approved_at?: string | null;
};

type Props = {
    quotes: {
        data: Quote[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function SalesQuotesIndex({ quotes }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('sales.quotes.manage');

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.sales, { title: 'Quotes', href: '/company/sales/quotes' },)}
        >
            <Head title="Sales Quotes" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Quotes</h1>
                    <p className="text-sm text-muted-foreground">
                        Prepare quotes, route approvals, and confirm orders.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/sales" label="Back to sales" variant="outline" />
                    {canManage && (
                        <Button asChild>
                            <Link href="/company/sales/quotes/create">New quote</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[980px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Quote #</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Partner</th>
                            <th className="px-4 py-3 font-medium">Lead</th>
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">
                                Valid until
                            </th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">
                                Approval
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {quotes.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={9}
                                >
                                    No quotes yet.
                                </td>
                            </tr>
                        )}
                        {quotes.data.map((quote) => (
                            <tr key={quote.id}>
                                <td className="px-4 py-3 font-medium">
                                    {quote.quote_number}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {quote.status}
                                </td>
                                <td className="px-4 py-3">
                                    {quote.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {quote.lead_title ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {quote.quote_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {quote.valid_until ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {quote.grand_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {quote.requires_approval
                                        ? quote.approved_at
                                            ? 'Approved'
                                            : 'Required'
                                        : 'Not required'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/sales/quotes/${quote.id}/edit`}
                                        className="text-sm font-medium text-primary"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {quotes.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {quotes.links.map((link) => (
                        <Link
                            key={link.label}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1 text-sm ${
                                link.active
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
