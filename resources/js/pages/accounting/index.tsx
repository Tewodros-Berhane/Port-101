import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Invoice = {
    id: string;
    invoice_number: string;
    document_type: string;
    status: string;
    delivery_status: string;
    partner_name?: string | null;
    sales_order_number?: string | null;
    invoice_date?: string | null;
    balance_due: number;
};

type Payment = {
    id: string;
    payment_number: string;
    status: string;
    invoice_number?: string | null;
    partner_name?: string | null;
    payment_date?: string | null;
    amount: number;
};

type Props = {
    kpis: {
        chart_of_accounts: number;
        journals: number;
        ledger_entries_30d: number;
        cash_balance: number;
        draft_invoices: number;
        posted_invoices: number;
        overdue_invoices: number;
        open_receivables: number;
        posted_payments_30d: number;
        reconciled_payments_30d: number;
    };
    statementSnapshot: {
        revenue: number;
        expenses: number;
        net_income: number;
        assets: number;
        liabilities: number;
        equity: number;
    };
    recentInvoices: Invoice[];
    recentPayments: Payment[];
};

export default function AccountingDashboard({
    kpis,
    statementSnapshot,
    recentInvoices,
    recentPayments,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Accounting', href: '/company/accounting' },
            ]}
        >
            <Head title="Accounting" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Accounting module</h1>
                    <p className="text-sm text-muted-foreground">
                        Invoice lifecycle, payments, and reconciliation.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link href="/company/accounting/invoices/create">
                            New invoice
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/payments/create">
                            New payment
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/statements">
                            Statements
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/manual-journals">
                            Manual journals
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Chart of accounts"
                    value={String(kpis.chart_of_accounts)}
                />
                <MetricCard label="Journals" value={String(kpis.journals)} />
                <MetricCard
                    label="Ledger entries (30d)"
                    value={String(kpis.ledger_entries_30d)}
                />
                <MetricCard
                    label="Cash balance"
                    value={kpis.cash_balance.toFixed(2)}
                />
                <MetricCard
                    label="Draft invoices"
                    value={String(kpis.draft_invoices)}
                />
                <MetricCard
                    label="Posted invoices"
                    value={String(kpis.posted_invoices)}
                />
                <MetricCard
                    label="Overdue invoices"
                    value={String(kpis.overdue_invoices)}
                />
                <MetricCard
                    label="Open receivables"
                    value={kpis.open_receivables.toFixed(2)}
                />
                <MetricCard
                    label="Posted payments (30d)"
                    value={String(kpis.posted_payments_30d)}
                />
                <MetricCard
                    label="Reconciled payments (30d)"
                    value={String(kpis.reconciled_payments_30d)}
                />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Ledger foundations
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Inspect the chart of accounts, journals, and
                                generated ledger entries behind invoice and
                                payment postings.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/company/accounting/accounts">
                                    Accounts
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/company/accounting/journals">
                                    Journals
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/company/accounting/ledger">
                                    Ledger
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/company/accounting/manual-journals">
                                    Manual journals
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">
                        Statement snapshot
                    </h2>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <SnapshotLine
                            label="Revenue"
                            value={statementSnapshot.revenue}
                        />
                        <SnapshotLine
                            label="Expenses"
                            value={statementSnapshot.expenses}
                        />
                        <SnapshotLine
                            label="Net income"
                            value={statementSnapshot.net_income}
                        />
                        <SnapshotLine
                            label="Assets"
                            value={statementSnapshot.assets}
                        />
                        <SnapshotLine
                            label="Liabilities"
                            value={statementSnapshot.liabilities}
                        />
                        <SnapshotLine
                            label="Equity"
                            value={statementSnapshot.equity}
                        />
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold">
                            Recent invoices
                        </h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/accounting/invoices">
                                Open
                            </Link>
                        </Button>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Invoice
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Partner
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Delivery
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentInvoices.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No invoices yet.
                                        </td>
                                    </tr>
                                )}
                                {recentInvoices.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/company/accounting/invoices/${invoice.id}/edit`}
                                                className="font-medium text-primary"
                                            >
                                                {invoice.invoice_number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            {invoice.partner_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 capitalize">
                                            {invoice.status.replace('_', ' ')}
                                        </td>
                                        <td className="px-3 py-2 capitalize">
                                            {invoice.delivery_status.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {invoice.balance_due.toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold">
                            Recent payments
                        </h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/accounting/payments">
                                Open
                            </Link>
                        </Button>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[700px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Payment
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Invoice
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Partner
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentPayments.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No payments yet.
                                        </td>
                                    </tr>
                                )}
                                {recentPayments.map((payment) => (
                                    <tr key={payment.id}>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/company/accounting/payments/${payment.id}/edit`}
                                                className="font-medium text-primary"
                                            >
                                                {payment.payment_number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            {payment.invoice_number ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {payment.partner_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 capitalize">
                                            {payment.status}
                                        </td>
                                        <td className="px-3 py-2">
                                            {payment.amount.toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function SnapshotLine({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border px-3 py-3">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-lg font-semibold">{value.toFixed(2)}</p>
        </div>
    );
}
