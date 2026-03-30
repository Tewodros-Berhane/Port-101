import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Payment = {
    id: string;
    payment_number: string;
    status: string;
    invoice_id: string;
    invoice_number?: string | null;
    partner_name?: string | null;
    payment_date?: string | null;
    amount: number;
    method?: string | null;
    reference?: string | null;
};

type Props = {
    payments: {
        data: Payment[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function AccountingPaymentsIndex({ payments }: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, { title: 'Payments', href: '/company/accounting/payments' },)}
        >
            <Head title="Accounting Payments" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Payments</h1>
                    <p className="text-sm text-muted-foreground">
                        Post, reconcile, and reverse invoice payments.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/accounting" label="Back to accounting" variant="outline" />
                    <Button asChild>
                        <Link href="/company/accounting/payments/create">
                            New payment
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[980px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Payment</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Invoice</th>
                            <th className="px-4 py-3 font-medium">Partner</th>
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">Method</th>
                            <th className="px-4 py-3 font-medium">Reference</th>
                            <th className="px-4 py-3 font-medium">Amount</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {payments.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={9}
                                >
                                    No payments found.
                                </td>
                            </tr>
                        )}
                        {payments.data.map((payment) => (
                            <tr key={payment.id}>
                                <td className="px-4 py-3 font-medium">
                                    {payment.payment_number}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {payment.status}
                                </td>
                                <td className="px-4 py-3">
                                    {payment.invoice_number ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {payment.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {payment.payment_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {payment.method ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {payment.reference ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {payment.amount.toFixed(2)}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/accounting/payments/${payment.id}/edit`}
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

            {payments.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {payments.links.map((link) => (
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
