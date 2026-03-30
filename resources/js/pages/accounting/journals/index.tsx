import { Head, Link, useForm } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type JournalRow = {
    id: string;
    code: string;
    name: string;
    journal_type: string;
    default_account?: string | null;
    currency_code?: string | null;
    is_active: boolean;
    entry_count: number;
    debit_total: number;
    credit_total: number;
};

type Props = {
    filters: {
        search: string;
        journal_type: string;
    };
    summary: {
        total_journals: number;
        bank_journals: number;
        ledger_entries: number;
    };
    journals: JournalRow[];
};

export default function AccountingJournalsIndex({
    filters,
    summary,
    journals,
}: Props) {
    const form = useForm({
        search: filters.search ?? '',
        journal_type: filters.journal_type ?? '',
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, { title: 'Journals', href: '/company/accounting/journals' },)}
        >
            <Head title="Accounting Journals" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Journals</h1>
                    <p className="text-sm text-muted-foreground">
                        Posting channels used by invoices, bills, and payments.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/accounting" label="Back to accounting" variant="outline" />
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/accounts">
                            Accounts
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/ledger">
                            General ledger
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-3">
                <MetricCard
                    label="Total journals"
                    value={String(summary.total_journals)}
                />
                <MetricCard
                    label="Bank journals"
                    value={String(summary.bank_journals)}
                />
                <MetricCard
                    label="Ledger entries"
                    value={String(summary.ledger_entries)}
                />
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/company/accounting/journals', {
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
                            placeholder="Code or journal name"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="journal_type">Journal type</Label>
                        <select
                            id="journal_type"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.journal_type}
                            onChange={(event) =>
                                form.setData('journal_type', event.target.value)
                            }
                        >
                            <option value="">All journal types</option>
                            <option value="sales">Sales</option>
                            <option value="purchase">Purchase</option>
                            <option value="bank">Bank</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Apply filters</Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href="/company/accounting/journals">
                                Reset
                            </Link>
                        </Button>
                    </div>
                </div>
            </form>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1080px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Code</th>
                            <th className="px-4 py-3 font-medium">Journal</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">
                                Default account
                            </th>
                            <th className="px-4 py-3 font-medium">Currency</th>
                            <th className="px-4 py-3 font-medium">Entries</th>
                            <th className="px-4 py-3 font-medium">Debit</th>
                            <th className="px-4 py-3 font-medium">Credit</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {journals.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={9}
                                >
                                    No journals found.
                                </td>
                            </tr>
                        )}
                        {journals.map((journal) => (
                            <tr key={journal.id}>
                                <td className="px-4 py-3 font-medium">
                                    {journal.code}
                                </td>
                                <td className="px-4 py-3">{journal.name}</td>
                                <td className="px-4 py-3 capitalize">
                                    {journal.journal_type}
                                </td>
                                <td className="px-4 py-3">
                                    {journal.default_account ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {journal.currency_code ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {journal.entry_count}
                                </td>
                                <td className="px-4 py-3">
                                    {journal.debit_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {journal.credit_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {journal.is_active ? 'Active' : 'Inactive'}
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
