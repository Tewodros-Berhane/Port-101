import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Invoice = {
    id: string;
    invoice_number: string;
    document_type: string;
    status: string;
    delivery_status: string;
    partner_name?: string | null;
    partner_type?: string | null;
    sales_order_number?: string | null;
    invoice_date?: string | null;
    due_date?: string | null;
    grand_total: number;
    paid_total: number;
    balance_due: number;
};

type Props = {
    invoices: {
        data: Invoice[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function AccountingInvoicesIndex({ invoices }: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, { title: 'Invoices', href: '/company/accounting/invoices' },)}
        >
            <Head title="Accounting Invoices" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Invoices</h1>
                    <p className="text-sm text-muted-foreground">
                        Customer invoices and vendor bills with posting states.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/accounting" label="Back to accounting" variant="outline" />
                    <Button asChild>
                        <Link href="/company/accounting/invoices/create">
                            New invoice
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1300px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Invoice</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Delivery</th>
                            <th className="px-4 py-3 font-medium">Partner</th>
                            <th className="px-4 py-3 font-medium">Sales order</th>
                            <th className="px-4 py-3 font-medium">Invoice date</th>
                            <th className="px-4 py-3 font-medium">Due date</th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">Paid</th>
                            <th className="px-4 py-3 font-medium">Balance</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {invoices.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={12}
                                >
                                    No invoices found.
                                </td>
                            </tr>
                        )}
                        {invoices.data.map((invoice) => (
                            <tr key={invoice.id}>
                                <td className="px-4 py-3 font-medium">
                                    {invoice.invoice_number}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {invoice.document_type.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {invoice.status.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {invoice.delivery_status.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.sales_order_number ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.invoice_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.due_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.grand_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.paid_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {invoice.balance_due.toFixed(2)}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/accounting/invoices/${invoice.id}/edit`}
                                        className="font-medium text-primary"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {invoices.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {invoices.links.map((link) => (
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
