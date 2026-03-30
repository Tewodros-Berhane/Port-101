import { Head, Link, useForm } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type AccountRow = {
    id: string;
    code: string;
    name: string;
    account_type: string;
    category: string;
    normal_balance: string;
    is_active: boolean;
    is_system: boolean;
    entry_count: number;
    debit_total: number;
    credit_total: number;
    balance: number;
};

type Props = {
    filters: {
        search: string;
        account_type: string;
    };
    summary: {
        total_accounts: number;
        active_accounts: number;
        system_accounts: number;
        cash_balance: number;
    };
    accounts: AccountRow[];
};

export default function AccountingAccountsIndex({
    filters,
    summary,
    accounts,
}: Props) {
    const form = useForm({
        search: filters.search ?? '',
        account_type: filters.account_type ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, { title: 'Accounts', href: '/company/accounting/accounts' },)}
        >
            <Head title="Chart of Accounts" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Chart of accounts</h1>
                    <p className="text-sm text-muted-foreground">
                        System default accounts and their live ledger balances.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/accounting" label="Back to accounting" variant="outline" />
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/journals">
                            Journals
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/statements">
                            Financial statements
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Total accounts"
                    value={String(summary.total_accounts)}
                />
                <MetricCard
                    label="Active accounts"
                    value={String(summary.active_accounts)}
                />
                <MetricCard
                    label="System accounts"
                    value={String(summary.system_accounts)}
                />
                <MetricCard
                    label="Cash balance"
                    value={summary.cash_balance.toFixed(2)}
                />
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/company/accounting/accounts', {
                        preserveState: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                            placeholder="Code or account name"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="account_type">Account type</Label>
                        <select
                            id="account_type"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.account_type}
                            onChange={(event) =>
                                form.setData('account_type', event.target.value)
                            }
                        >
                            <option value="">All account types</option>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Apply filters</Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href="/company/accounting/accounts">
                                Reset
                            </Link>
                        </Button>
                    </div>
                </div>
            </form>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1180px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Code</th>
                            <th className="px-4 py-3 font-medium">Account</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Category</th>
                            <th className="px-4 py-3 font-medium">Normal</th>
                            <th className="px-4 py-3 font-medium">Entries</th>
                            <th className="px-4 py-3 font-medium">Debit</th>
                            <th className="px-4 py-3 font-medium">Credit</th>
                            <th className="px-4 py-3 font-medium">Balance</th>
                            <th className="px-4 py-3 font-medium">Flags</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {accounts.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={10}
                                >
                                    No accounts found.
                                </td>
                            </tr>
                        )}
                        {accounts.map((account) => (
                            <tr key={account.id}>
                                <td className="px-4 py-3 font-medium">
                                    {account.code}
                                </td>
                                <td className="px-4 py-3">{account.name}</td>
                                <td className="px-4 py-3 capitalize">
                                    {account.account_type}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {account.category.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {account.normal_balance}
                                </td>
                                <td className="px-4 py-3">
                                    {account.entry_count}
                                </td>
                                <td className="px-4 py-3">
                                    {account.debit_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {account.credit_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {account.balance.toFixed(2)}
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {account.is_system ? 'System' : 'Custom'}
                                    {' � '}
                                    {account.is_active ? 'Active' : 'Inactive'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
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
