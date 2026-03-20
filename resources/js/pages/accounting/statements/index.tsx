import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type StatementRow = {
    account_id: string;
    code: string;
    name: string;
    amount: number;
};

type TrialBalanceRow = {
    account_id: string;
    code: string;
    name: string;
    account_type: string;
    debit: number;
    credit: number;
    net_balance: number;
};

type Props = {
    filters: {
        start_date: string;
        end_date: string;
    };
    currencyCode?: string | null;
    canExport: boolean;
    statements: {
        start_date: string;
        end_date: string;
        snapshot: {
            revenue: number;
            expenses: number;
            net_income: number;
            assets: number;
            liabilities: number;
            equity: number;
            cash_balance: number;
        };
        profit_and_loss: {
            revenue: StatementRow[];
            expenses: StatementRow[];
            total_revenue: number;
            total_expenses: number;
            net_income: number;
        };
        balance_sheet: {
            assets: StatementRow[];
            liabilities: StatementRow[];
            equity: StatementRow[];
            total_assets: number;
            total_liabilities: number;
            total_equity: number;
            balance_gap: number;
        };
        cash_flow: {
            opening_cash_balance: number;
            cash_inflows: number;
            cash_outflows: number;
            net_cash_movement: number;
            closing_cash_balance: number;
        };
        trial_balance: {
            rows: TrialBalanceRow[];
            total_debit: number;
            total_credit: number;
            out_of_balance: number;
        };
    };
};

export default function AccountingStatementsIndex({
    filters,
    currencyCode,
    canExport,
    statements,
}: Props) {
    const form = useForm({
        start_date: filters.start_date ?? '',
        end_date: filters.end_date ?? '',
    });

    const query = new URLSearchParams({
        start_date: form.data.start_date,
        end_date: form.data.end_date,
    }).toString();

    const formatCurrency = (value: number) =>
        new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currencyCode || 'USD',
            maximumFractionDigits: 2,
        }).format(value || 0);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Accounting', href: '/company/accounting' },
                {
                    title: 'Financial Statements',
                    href: '/company/accounting/statements',
                },
            ]}
        >
            <Head title="Financial Statements" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Financial statements
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Profit and loss, balance sheet, cash flow summary, and
                        trial balance.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/ledger">
                            General ledger
                        </Link>
                    </Button>
                    {canExport && (
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={`/company/reports/export/financial-profit-loss?${query}&format=pdf`}
                                >
                                    Export P&amp;L PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={`/company/reports/export/financial-trial-balance?${query}&format=xlsx`}
                                >
                                    Export trial balance XLSX
                                </a>
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/company/accounting/statements', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="start_date">Start date</Label>
                        <Input
                            id="start_date"
                            type="date"
                            value={form.data.start_date}
                            onChange={(event) =>
                                form.setData('start_date', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="end_date">End date</Label>
                        <Input
                            id="end_date"
                            type="date"
                            value={form.data.end_date}
                            onChange={(event) =>
                                form.setData('end_date', event.target.value)
                            }
                        />
                    </div>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Apply window</Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href="/company/accounting/statements">
                                Reset
                            </Link>
                        </Button>
                    </div>
                </div>
            </form>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Revenue"
                    value={formatCurrency(statements.snapshot.revenue)}
                />
                <MetricCard
                    label="Expenses"
                    value={formatCurrency(statements.snapshot.expenses)}
                />
                <MetricCard
                    label="Net income"
                    value={formatCurrency(statements.snapshot.net_income)}
                />
                <MetricCard
                    label="Cash balance"
                    value={formatCurrency(statements.snapshot.cash_balance)}
                />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                <StatementPanel
                    title="Profit and loss"
                    rows={[
                        ...statements.profit_and_loss.revenue,
                        ...statements.profit_and_loss.expenses,
                    ]}
                    footerLabel="Net income"
                    footerValue={formatCurrency(
                        statements.profit_and_loss.net_income,
                    )}
                    formatCurrency={formatCurrency}
                />
                <div className="rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Cash flow summary</h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <CashMetric
                            label="Opening cash"
                            value={formatCurrency(
                                statements.cash_flow.opening_cash_balance,
                            )}
                        />
                        <CashMetric
                            label="Cash inflows"
                            value={formatCurrency(
                                statements.cash_flow.cash_inflows,
                            )}
                        />
                        <CashMetric
                            label="Cash outflows"
                            value={formatCurrency(
                                statements.cash_flow.cash_outflows,
                            )}
                        />
                        <CashMetric
                            label="Closing cash"
                            value={formatCurrency(
                                statements.cash_flow.closing_cash_balance,
                            )}
                        />
                    </div>
                    <div className="mt-4 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                        Net cash movement:{' '}
                        <span className="font-medium text-foreground">
                            {formatCurrency(
                                statements.cash_flow.net_cash_movement,
                            )}
                        </span>
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-3">
                <StatementPanel
                    title="Assets"
                    rows={statements.balance_sheet.assets}
                    footerLabel="Total assets"
                    footerValue={formatCurrency(
                        statements.balance_sheet.total_assets,
                    )}
                    formatCurrency={formatCurrency}
                />
                <StatementPanel
                    title="Liabilities"
                    rows={statements.balance_sheet.liabilities}
                    footerLabel="Total liabilities"
                    footerValue={formatCurrency(
                        statements.balance_sheet.total_liabilities,
                    )}
                    formatCurrency={formatCurrency}
                />
                <StatementPanel
                    title="Equity"
                    rows={statements.balance_sheet.equity}
                    footerLabel="Total equity"
                    footerValue={formatCurrency(
                        statements.balance_sheet.total_equity,
                    )}
                    formatCurrency={formatCurrency}
                />
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">Trial balance</h2>
                        <p className="text-xs text-muted-foreground">
                            Debits and credits must remain balanced.
                        </p>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        Out of balance:{' '}
                        <span className="font-medium text-foreground">
                            {formatCurrency(
                                statements.trial_balance.out_of_balance,
                            )}
                        </span>
                    </div>
                </div>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[980px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">
                                    Account
                                </th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">Debit</th>
                                <th className="px-4 py-3 font-medium">
                                    Credit
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Net balance
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {statements.trial_balance.rows.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={6}
                                    >
                                        No ledger activity in this window.
                                    </td>
                                </tr>
                            )}
                            {statements.trial_balance.rows.map((row) => (
                                <tr key={row.account_id}>
                                    <td className="px-4 py-3 font-medium">
                                        {row.code}
                                    </td>
                                    <td className="px-4 py-3">{row.name}</td>
                                    <td className="px-4 py-3 capitalize">
                                        {row.account_type}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(row.debit)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(row.credit)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(row.net_balance)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="bg-muted/40">
                            <tr>
                                <td
                                    className="px-4 py-3 font-medium"
                                    colSpan={3}
                                >
                                    Totals
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {formatCurrency(
                                        statements.trial_balance.total_debit,
                                    )}
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {formatCurrency(
                                        statements.trial_balance.total_credit,
                                    )}
                                </td>
                                <td className="px-4 py-3" />
                            </tr>
                        </tfoot>
                    </table>
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

function CashMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-lg font-semibold">{value}</p>
        </div>
    );
}

function StatementPanel({
    title,
    rows,
    footerLabel,
    footerValue,
    formatCurrency,
}: {
    title: string;
    rows: StatementRow[];
    footerLabel: string;
    footerValue: string;
    formatCurrency: (value: number) => string;
}) {
    return (
        <div className="rounded-xl border p-4">
            <h2 className="text-sm font-semibold">{title}</h2>
            <div className="mt-4 space-y-3">
                {rows.length === 0 && (
                    <div className="rounded-lg border border-dashed px-4 py-6 text-sm text-muted-foreground">
                        No activity in this section.
                    </div>
                )}
                {rows.map((row) => (
                    <div
                        key={row.account_id}
                        className="flex items-start justify-between gap-4 rounded-lg border px-4 py-3"
                    >
                        <div>
                            <p className="font-medium">
                                {row.code} � {row.name}
                            </p>
                        </div>
                        <p className="font-medium">
                            {formatCurrency(row.amount)}
                        </p>
                    </div>
                ))}
            </div>
            <div className="mt-4 flex items-center justify-between rounded-lg bg-muted/40 px-4 py-3 text-sm">
                <span className="font-medium">{footerLabel}</span>
                <span className="font-semibold">{footerValue}</span>
            </div>
        </div>
    );
}
