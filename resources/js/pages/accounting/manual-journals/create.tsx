import { Head, useForm } from '@inertiajs/react';
import AccountingManualJournalLinesEditor, {
    type AccountingManualJournalLineInput,
} from '@/components/accounting/manual-journal-lines-editor';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type JournalOption = {
    id: string;
    code: string;
    name: string;
};

type AccountOption = {
    id: string;
    code: string;
    name: string;
    account_type: string;
};

type Props = {
    manualJournal: {
        journal_id: string;
        entry_date: string;
        reference: string;
        description: string;
        lines: AccountingManualJournalLineInput[];
    };
    journals: JournalOption[];
    accounts: AccountOption[];
};

export default function AccountingManualJournalCreate({
    manualJournal,
    journals,
    accounts,
}: Props) {
    const form = useForm({
        journal_id: manualJournal.journal_id,
        entry_date: manualJournal.entry_date,
        reference: manualJournal.reference,
        description: manualJournal.description,
        lines: manualJournal.lines,
    });

    const totals = calculateTotals(form.data.lines);

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, {
                    title: 'Manual Journals',
                    href: '/company/accounting/manual-journals',
                },
                {
                    title: 'Create',
                    href: '/company/accounting/manual-journals/create',
                },)}
        >
            <Head title="New Manual Journal" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New manual journal</h1>
                    <p className="text-sm text-muted-foreground">
                        Record balanced general ledger adjustments.
                    </p>
                </div>
                <BackLinkAction href="/company/accounting/manual-journals" label="Back to manual journals" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/accounting/manual-journals');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2 xl:col-span-2">
                        <Label htmlFor="journal_id">General journal</Label>
                        <select
                            id="journal_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.journal_id}
                            onChange={(event) =>
                                form.setData('journal_id', event.target.value)
                            }
                        >
                            <option value="">Select journal</option>
                            {journals.map((journal) => (
                                <option key={journal.id} value={journal.id}>
                                    {journal.code} - {journal.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.journal_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="entry_date">Entry date</Label>
                        <Input
                            id="entry_date"
                            type="date"
                            value={form.data.entry_date}
                            onChange={(event) =>
                                form.setData('entry_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.entry_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="reference">Reference</Label>
                        <Input
                            id="reference"
                            value={form.data.reference}
                            onChange={(event) =>
                                form.setData('reference', event.target.value)
                            }
                        />
                        <InputError message={form.errors.reference} />
                    </div>

                    <div className="grid gap-2 xl:col-span-4">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                <AccountingManualJournalLinesEditor
                    lines={form.data.lines}
                    accounts={accounts}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={form.processing}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-3">
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Total debit
                        </p>
                        <p className="font-semibold">
                            {totals.totalDebit.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Total credit
                        </p>
                        <p className="font-semibold">
                            {totals.totalCredit.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Balance</p>
                        <p className="font-semibold">
                            {totals.balance.toFixed(2)}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Create manual journal
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function calculateTotals(lines: AccountingManualJournalLineInput[]) {
    return lines.reduce(
        (accumulator, line) => ({
            totalDebit: accumulator.totalDebit + (Number(line.debit) || 0),
            totalCredit: accumulator.totalCredit + (Number(line.credit) || 0),
            balance:
                accumulator.balance +
                ((Number(line.debit) || 0) - (Number(line.credit) || 0)),
        }),
        { totalDebit: 0, totalCredit: 0, balance: 0 },
    );
}
