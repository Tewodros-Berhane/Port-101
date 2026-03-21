import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';

type JournalOption = {
    id: string;
    code: string;
    name: string;
};

type EligiblePayment = {
    id: string;
    payment_number: string;
    status: string;
    invoice_number?: string | null;
    partner_name?: string | null;
    payment_date?: string | null;
    amount: number;
    method?: string | null;
    reference?: string | null;
};

type BatchRow = {
    id: string;
    statement_reference: string;
    statement_date?: string | null;
    journal_name?: string | null;
    journal_code?: string | null;
    item_count: number;
    total_amount: number;
    reconciled_at?: string | null;
    reconciled_by?: string | null;
    unreconciled_at?: string | null;
    unreconciled_by?: string | null;
    unreconcile_reason?: string | null;
    can_unreconcile: boolean;
};

type Props = {
    filters: {
        journal_id: string;
        statement_reference: string;
        statement_date: string;
        notes: string;
    };
    bankJournals: JournalOption[];
    eligiblePayments: EligiblePayment[];
    recentBatches: BatchRow[];
};

export default function AccountingBankReconciliationIndex({
    filters,
    bankJournals,
    eligiblePayments,
    recentBatches,
}: Props) {
    const { hasPermission } = usePermissions();
    const form = useForm({
        journal_id: filters.journal_id,
        statement_reference: filters.statement_reference,
        statement_date: filters.statement_date,
        notes: filters.notes,
        payment_ids: [] as string[],
    });
    const canManage = hasPermission('accounting.bank_reconciliation.manage');

    const selectedTotal = eligiblePayments
        .filter((payment) => form.data.payment_ids.includes(payment.id))
        .reduce((total, payment) => total + payment.amount, 0);

    const allSelected =
        eligiblePayments.length > 0 &&
        eligiblePayments.every((payment) =>
            form.data.payment_ids.includes(payment.id),
        );

    const togglePayment = (paymentId: string, checked: boolean) => {
        form.setData(
            'payment_ids',
            checked
                ? [...form.data.payment_ids, paymentId]
                : form.data.payment_ids.filter((id) => id !== paymentId),
        );
    };

    const toggleAll = (checked: boolean) => {
        form.setData(
            'payment_ids',
            checked ? eligiblePayments.map((payment) => payment.id) : [],
        );
    };

    const handleUnreconcile = (batch: BatchRow) => {
        const reason = window.prompt(
            `Unreconcile ${batch.statement_reference}. Enter a reason for the audit trail.`,
        );

        if (!reason || reason.trim() === '') {
            return;
        }

        router.post(
            `/company/accounting/bank-reconciliation/${batch.id}/unreconcile`,
            { reason: reason.trim() },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Accounting', href: '/company/accounting' },
                {
                    title: 'Bank Reconciliation',
                    href: '/company/accounting/bank-reconciliation',
                },
            ]}
        >
            <Head title="Bank Reconciliation" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Bank reconciliation
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Match posted payments to a bank statement batch without
                        overloading invoice reconciliation state.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/payments">Payments</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/ledger">Ledger</Link>
                    </Button>
                </div>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/accounting/bank-reconciliation');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2 xl:col-span-2">
                        <Label htmlFor="journal_id">Bank journal</Label>
                        <select
                            id="journal_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.journal_id}
                            onChange={(event) =>
                                form.setData('journal_id', event.target.value)
                            }
                        >
                            <option value="">Select bank journal</option>
                            {bankJournals.map((journal) => (
                                <option key={journal.id} value={journal.id}>
                                    {journal.code} - {journal.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.journal_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="statement_reference">
                            Statement reference
                        </Label>
                        <Input
                            id="statement_reference"
                            value={form.data.statement_reference}
                            onChange={(event) =>
                                form.setData(
                                    'statement_reference',
                                    event.target.value,
                                )
                            }
                            placeholder="e.g. BANK-2026-03"
                        />
                        <InputError message={form.errors.statement_reference} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="statement_date">Statement date</Label>
                        <Input
                            id="statement_date"
                            type="date"
                            value={form.data.statement_date}
                            onChange={(event) =>
                                form.setData(
                                    'statement_date',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.statement_date} />
                    </div>

                    <div className="grid gap-2 xl:col-span-4">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Eligible payments
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Select posted payments that appear on the bank
                                statement.
                            </p>
                        </div>
                        <div className="flex items-center gap-4 text-sm">
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={allSelected}
                                    onCheckedChange={(checked) =>
                                        toggleAll(Boolean(checked))
                                    }
                                />
                                <span>Select all</span>
                            </label>
                            <span className="font-medium">
                                Selected total: {selectedTotal.toFixed(2)}
                            </span>
                        </div>
                    </div>

                    <InputError message={form.errors.payment_ids} />

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[980px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Select
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Payment
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Invoice
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Partner
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Method
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Reference
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {eligiblePayments.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={8}
                                        >
                                            No unreconciled posted payments are
                                            available.
                                        </td>
                                    </tr>
                                )}
                                {eligiblePayments.map((payment) => {
                                    const checked = form.data.payment_ids.includes(
                                        payment.id,
                                    );

                                    return (
                                        <tr key={payment.id}>
                                            <td className="px-4 py-3">
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={(value) =>
                                                        togglePayment(
                                                            payment.id,
                                                            Boolean(value),
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                <Link
                                                    href={`/company/accounting/payments/${payment.id}/edit`}
                                                    className="text-primary"
                                                >
                                                    {payment.payment_number}
                                                </Link>
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
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 text-sm">
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Statement batch total
                        </p>
                        <p className="text-lg font-semibold">
                            {selectedTotal.toFixed(2)}
                        </p>
                    </div>
                    <Button
                        type="submit"
                        disabled={form.processing || form.data.payment_ids.length === 0}
                    >
                        Create reconciliation batch
                    </Button>
                </div>
            </form>

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">Recent batches</h2>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Statement
                                </th>
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 font-medium">
                                    Journal
                                </th>
                                <th className="px-4 py-3 font-medium">Items</th>
                                <th className="px-4 py-3 font-medium">Total</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Reconciliation
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {recentBatches.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={8}
                                    >
                                        No bank reconciliation batches created
                                        yet.
                                    </td>
                                </tr>
                            )}
                            {recentBatches.map((batch) => (
                                <tr key={batch.id}>
                                    <td className="px-4 py-3 font-medium">
                                        {batch.statement_reference}
                                    </td>
                                    <td className="px-4 py-3">
                                        {batch.statement_date ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {batch.journal_code
                                            ? `${batch.journal_code} - ${batch.journal_name}`
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {batch.item_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        {batch.total_amount.toFixed(2)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {batch.unreconciled_at
                                            ? 'Unreconciled'
                                            : 'Reconciled'}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        <p>
                                            {batch.reconciled_by
                                                ? `${batch.reconciled_by} · ${formatDateTime(batch.reconciled_at)}`
                                                : formatDateTime(batch.reconciled_at)}
                                        </p>
                                        {batch.unreconciled_at && (
                                            <>
                                                <p className="mt-1">
                                                    {batch.unreconciled_by
                                                        ? `${batch.unreconciled_by} · ${formatDateTime(batch.unreconciled_at)}`
                                                        : formatDateTime(batch.unreconciled_at)}
                                                </p>
                                                <p className="mt-1">
                                                    {batch.unreconcile_reason ??
                                                        '-'}
                                                </p>
                                            </>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {canManage && batch.can_unreconcile ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleUnreconcile(batch)
                                                }
                                            >
                                                Unreconcile
                                            </Button>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                {batch.unreconciled_at
                                                    ? 'Closed'
                                                    : '-'}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function formatDateTime(value?: string | null) {
    return value ? new Date(value).toLocaleString() : '-';
}
