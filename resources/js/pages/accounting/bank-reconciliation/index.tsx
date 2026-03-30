import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type JournalOption = {
    id: string;
    code: string;
    name: string;
};

type CandidatePayment = {
    id: string;
    payment_number?: string | null;
    reference?: string | null;
    invoice_number?: string | null;
    partner_name?: string | null;
    payment_date?: string | null;
    amount: number;
};

type ImportLine = {
    id: string;
    line_number: number;
    transaction_date?: string | null;
    reference?: string | null;
    description?: string | null;
    amount: number;
    match_status: string;
    payment_id?: string | null;
    payment_number?: string | null;
    payment_reference?: string | null;
    invoice_number?: string | null;
    partner_name?: string | null;
    candidate_payments: CandidatePayment[];
    can_manage_match: boolean;
};

type ActiveImport = {
    id: string;
    statement_reference: string;
    statement_date?: string | null;
    journal_name?: string | null;
    journal_code?: string | null;
    source_file_name: string;
    notes?: string | null;
    reconciled_batch_id?: string | null;
    reconciled_batch_reference?: string | null;
    metrics: {
        matched: number;
        unmatched: number;
        duplicate: number;
    };
    lines: ImportLine[];
};

type RecentImportRow = {
    id: string;
    statement_reference: string;
    statement_date?: string | null;
    journal_name?: string | null;
    journal_code?: string | null;
    source_file_name: string;
    matched_count: number;
    unmatched_count: number;
    duplicate_count: number;
    reconciled_batch_id?: string | null;
    reconciled_batch_reference?: string | null;
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
    importForm: {
        journal_id: string;
        statement_reference: string;
        statement_date: string;
        notes: string;
    };
    bankJournals: JournalOption[];
    activeImport?: ActiveImport | null;
    recentImports: RecentImportRow[];
    recentBatches: BatchRow[];
};

const statementTemplateDownloads = [
    {
        label: 'CSV template',
        href: '/templates/bank-statements/statement-template.csv',
    },
    {
        label: 'OFX sample',
        href: '/templates/bank-statements/statement-sample.ofx',
    },
    {
        label: 'CAMT sample',
        href: '/templates/bank-statements/statement-sample.camt.xml',
    },
] as const;

export default function AccountingBankReconciliationIndex({
    importForm: importDefaults,
    bankJournals,
    activeImport,
    recentImports,
    recentBatches,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('accounting.bank_reconciliation.manage');
    const [matchSelections, setMatchSelections] = useState<
        Record<string, string>
    >({});
    const importForm = useForm({
        journal_id: importDefaults.journal_id,
        statement_reference: importDefaults.statement_reference,
        statement_date: importDefaults.statement_date,
        notes: importDefaults.notes,
        file: null as File | null,
    });
    const reconcileForm = useForm({
        bank_statement_import_id: activeImport?.id ?? '',
        line_ids:
            activeImport?.lines
                .filter((line) => line.match_status === 'matched')
                .map((line) => line.id) ?? [],
    });

    const matchedLines =
        activeImport?.lines.filter((line) => line.match_status === 'matched') ??
        [];
    const exceptionLines =
        activeImport?.lines.filter((line) => line.match_status !== 'matched') ??
        [];

    const selectedTotal = matchedLines
        .filter((line) => reconcileForm.data.line_ids.includes(line.id))
        .reduce((total, line) => total + line.amount, 0);

    const allMatchedSelected =
        matchedLines.length > 0 &&
        matchedLines.every((line) => reconcileForm.data.line_ids.includes(line.id));

    const toggleMatchedLine = (lineId: string, checked: boolean) => {
        reconcileForm.setData(
            'line_ids',
            checked
                ? [...reconcileForm.data.line_ids, lineId]
                : reconcileForm.data.line_ids.filter((id) => id !== lineId),
        );
    };

    const toggleAllMatchedLines = (checked: boolean) => {
        reconcileForm.setData(
            'line_ids',
            checked ? matchedLines.map((line) => line.id) : [],
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

    const handleLineMatch = (line: ImportLine, paymentId?: string | null) => {
        router.post(
            `/company/accounting/bank-reconciliation/lines/${line.id}/match`,
            paymentId ? { payment_id: paymentId } : {},
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, {
                    title: 'Bank Reconciliation',
                    href: '/company/accounting/bank-reconciliation',
                },)}
        >
            <Head title="Bank Reconciliation" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Bank reconciliation
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Import bank statements, review matched lines, resolve
                        exceptions, and create reconciliation batches from the
                        statement itself.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/accounting" label="Back to accounting" variant="outline" />
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/payments">Payments</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/accounting/ledger">Ledger</Link>
                    </Button>
                </div>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    importForm.post('/company/accounting/bank-reconciliation/import', {
                        preserveScroll: true,
                        forceFormData: true,
                    });
                }}
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Import statement
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Upload CSV, OFX, or CAMT XML files. The matcher
                            maps exact references first, then falls back to
                            unique amount matches.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {statementTemplateDownloads.map((template) => (
                                <Button
                                    key={template.href}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a href={template.href} download>
                                        {template.label}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </div>
                    <Button type="submit" disabled={importForm.processing}>
                        Import statement
                    </Button>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div className="grid gap-2 xl:col-span-2">
                        <Label htmlFor="journal_id">Bank journal</Label>
                        <select
                            id="journal_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={importForm.data.journal_id}
                            onChange={(event) =>
                                importForm.setData('journal_id', event.target.value)
                            }
                        >
                            <option value="">Select bank journal</option>
                            {bankJournals.map((journal) => (
                                <option key={journal.id} value={journal.id}>
                                    {journal.code} - {journal.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={importForm.errors.journal_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="statement_reference">
                            Statement reference
                        </Label>
                        <Input
                            id="statement_reference"
                            value={importForm.data.statement_reference}
                            onChange={(event) =>
                                importForm.setData(
                                    'statement_reference',
                                    event.target.value,
                                )
                            }
                            placeholder="e.g. BANK-2026-03"
                        />
                        <InputError
                            message={importForm.errors.statement_reference}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="statement_date">Statement date</Label>
                        <Input
                            id="statement_date"
                            type="date"
                            value={importForm.data.statement_date}
                            onChange={(event) =>
                                importForm.setData(
                                    'statement_date',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={importForm.errors.statement_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="statement_file">Statement file</Label>
                        <input
                            id="statement_file"
                            type="file"
                            accept=".csv,.txt,.ofx,.xml,text/csv,text/xml,application/xml,application/ofx"
                            className="block h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            onChange={(event) =>
                                importForm.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={importForm.errors.file} />
                    </div>

                    <div className="grid gap-2 xl:col-span-5">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={importForm.data.notes}
                            onChange={(event) =>
                                importForm.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={importForm.errors.notes} />
                    </div>
                </div>
            </form>

            {activeImport && (
                <div className="mt-6 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Imported statement preview
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {activeImport.statement_reference} -{' '}
                                {activeImport.source_file_name}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/company/accounting/bank-reconciliation">
                                    Clear preview
                                </Link>
                            </Button>
                            {activeImport.reconciled_batch_id && (
                                <Button variant="outline" asChild>
                                    <Link href="/company/accounting/bank-reconciliation">
                                        Batch created
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            label="Matched"
                            value={String(activeImport.metrics.matched)}
                        />
                        <MetricCard
                            label="Unmatched"
                            value={String(activeImport.metrics.unmatched)}
                        />
                        <MetricCard
                            label="Duplicate"
                            value={String(activeImport.metrics.duplicate)}
                        />
                        <MetricCard
                            label="Selected total"
                            value={selectedTotal.toFixed(2)}
                        />
                    </div>

                    {activeImport.reconciled_batch_reference && (
                        <div className="mt-4 rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                            This import already created batch{' '}
                            <span className="font-medium text-foreground">
                                {activeImport.reconciled_batch_reference}
                            </span>
                            .
                        </div>
                    )}

                    <form
                        className="mt-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            reconcileForm.post(
                                '/company/accounting/bank-reconciliation',
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-4 text-sm">
                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={allMatchedSelected}
                                        onCheckedChange={(checked) =>
                                            toggleAllMatchedLines(Boolean(checked))
                                        }
                                        disabled={matchedLines.length === 0}
                                    />
                                    <span>Select all matched</span>
                                </label>
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    reconcileForm.processing ||
                                    reconcileForm.data.line_ids.length === 0 ||
                                    Boolean(activeImport.reconciled_batch_id)
                                }
                            >
                                Create reconciliation batch
                            </Button>
                        </div>

                        <InputError
                            message={
                                reconcileForm.errors.bank_statement_import_id ??
                                reconcileForm.errors.line_ids
                            }
                        />

                        <div className="mt-4 overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[1180px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Include
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Line
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Date
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Reference
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Description
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Amount
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Match
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
                                        <th className="px-4 py-3 text-right font-medium">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {activeImport.lines.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-4 py-8 text-center text-muted-foreground"
                                                colSpan={11}
                                            >
                                                No statement lines were imported.
                                            </td>
                                        </tr>
                                    )}
                                    {activeImport.lines.map((line) => {
                                        const isMatched =
                                            line.match_status === 'matched';
                                        const checked =
                                            reconcileForm.data.line_ids.includes(
                                                line.id,
                                            );

                                        return (
                                            <tr key={line.id}>
                                                <td className="px-4 py-3">
                                                    <Checkbox
                                                        checked={checked}
                                                        disabled={
                                                            !isMatched ||
                                                            Boolean(
                                                                activeImport.reconciled_batch_id,
                                                            )
                                                        }
                                                        onCheckedChange={(value) =>
                                                            toggleMatchedLine(
                                                                line.id,
                                                                Boolean(value),
                                                            )
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.line_number}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.transaction_date ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.reference ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.description ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.amount.toFixed(2)}
                                                </td>
                                                <td className="px-4 py-3 capitalize">
                                                    {line.match_status}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.payment_number ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.invoice_number ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {line.partner_name ?? '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {line.can_manage_match &&
                                                    line.payment_id &&
                                                    !activeImport.reconciled_batch_id ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleLineMatch(
                                                                    line,
                                                                    null,
                                                                )
                                                            }
                                                        >
                                                            Clear match
                                                        </Button>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            {line.match_status ===
                                                            'matched'
                                                                ? 'Ready'
                                                                : 'Review'}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </form>

                    {exceptionLines.length > 0 && (
                        <div className="mt-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Resolve exceptions
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Match unmatched or duplicate statement
                                        lines before creating the reconciliation
                                        batch.
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 grid gap-4 xl:grid-cols-2">
                                {exceptionLines.map((line) => {
                                    const selectedPaymentId =
                                        matchSelections[line.id] ??
                                        line.payment_id ??
                                        line.candidate_payments[0]?.id ??
                                        '';

                                    return (
                                        <div
                                            key={line.id}
                                            className="rounded-xl border p-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-semibold">
                                                        Line {line.line_number}
                                                    </p>
                                                    <p className="mt-1 text-xs uppercase tracking-wide text-muted-foreground">
                                                        {line.match_status}
                                                    </p>
                                                </div>
                                                <p className="text-sm font-medium">
                                                    {line.amount.toFixed(2)}
                                                </p>
                                            </div>

                                            <div className="mt-3 space-y-1 text-sm text-muted-foreground">
                                                <p>
                                                    Date:{' '}
                                                    <span className="text-foreground">
                                                        {line.transaction_date ??
                                                            '-'}
                                                    </span>
                                                </p>
                                                <p>
                                                    Reference:{' '}
                                                    <span className="text-foreground">
                                                        {line.reference ?? '-'}
                                                    </span>
                                                </p>
                                                <p>
                                                    Description:{' '}
                                                    <span className="text-foreground">
                                                        {line.description ?? '-'}
                                                    </span>
                                                </p>
                                            </div>

                                            <div className="mt-4 grid gap-2">
                                                <Label
                                                    htmlFor={`match-${line.id}`}
                                                >
                                                    Candidate payment
                                                </Label>
                                                <select
                                                    id={`match-${line.id}`}
                                                    className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                                                    value={selectedPaymentId}
                                                    onChange={(event) =>
                                                        setMatchSelections(
                                                            (
                                                                currentSelections,
                                                            ) => ({
                                                                ...currentSelections,
                                                                [line.id]:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                    disabled={
                                                        !line.can_manage_match ||
                                                        Boolean(
                                                            activeImport.reconciled_batch_id,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Select a payment
                                                    </option>
                                                    {line.candidate_payments.map(
                                                        (payment) => (
                                                            <option
                                                                key={payment.id}
                                                                value={payment.id}
                                                            >
                                                                {[
                                                                    payment.payment_number,
                                                                    payment.reference,
                                                                    payment.invoice_number,
                                                                    payment.partner_name,
                                                                    payment.payment_date,
                                                                    payment.amount.toFixed(
                                                                        2,
                                                                    ),
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' - ',
                                                                    )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                {line.candidate_payments.length ===
                                                    0 && (
                                                    <p className="text-xs text-muted-foreground">
                                                        No candidate payments
                                                        were suggested for this
                                                        line yet.
                                                    </p>
                                                )}
                                            </div>

                                            <div className="mt-4 flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        !line.can_manage_match ||
                                                        !selectedPaymentId ||
                                                        Boolean(
                                                            activeImport.reconciled_batch_id,
                                                        )
                                                    }
                                                    onClick={() =>
                                                        handleLineMatch(
                                                            line,
                                                            selectedPaymentId,
                                                        )
                                                    }
                                                >
                                                    Apply match
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        !line.can_manage_match ||
                                                        Boolean(
                                                            activeImport.reconciled_batch_id,
                                                        )
                                                    }
                                                    onClick={() =>
                                                        handleLineMatch(
                                                            line,
                                                            null,
                                                        )
                                                    }
                                                >
                                                    Leave unmatched
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">Recent imports</h2>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[1040px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Statement
                                </th>
                                <th className="px-4 py-3 font-medium">Date</th>
                                <th className="px-4 py-3 font-medium">
                                    Journal
                                </th>
                                <th className="px-4 py-3 font-medium">File</th>
                                <th className="px-4 py-3 font-medium">
                                    Matches
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {recentImports.length === 0 && (
                                <tr>
                                    <td
                                        className="px-4 py-8 text-center text-muted-foreground"
                                        colSpan={7}
                                    >
                                        No statement imports created yet.
                                    </td>
                                </tr>
                            )}
                            {recentImports.map((statementImport) => (
                                <tr key={statementImport.id}>
                                    <td className="px-4 py-3 font-medium">
                                        {statementImport.statement_reference}
                                    </td>
                                    <td className="px-4 py-3">
                                        {statementImport.statement_date ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {statementImport.journal_code
                                            ? `${statementImport.journal_code} - ${statementImport.journal_name}`
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {statementImport.source_file_name}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        <p>Matched: {statementImport.matched_count}</p>
                                        <p>
                                            Unmatched: {statementImport.unmatched_count}
                                        </p>
                                        <p>
                                            Duplicate: {statementImport.duplicate_count}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {statementImport.reconciled_batch_reference
                                            ? `Reconciled to ${statementImport.reconciled_batch_reference}`
                                            : 'Pending review'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link
                                                href={`/company/accounting/bank-reconciliation?import_id=${statementImport.id}`}
                                            >
                                                Review
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">Recent batches</h2>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[980px] text-sm">
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
                                                ? `${batch.reconciled_by} - ${formatDateTime(batch.reconciled_at)}`
                                                : formatDateTime(batch.reconciled_at)}
                                        </p>
                                        {batch.unreconciled_at && (
                                            <>
                                                <p className="mt-1">
                                                    {batch.unreconciled_by
                                                        ? `${batch.unreconciled_by} - ${formatDateTime(batch.unreconciled_at)}`
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

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function formatDateTime(value?: string | null) {
    return value ? new Date(value).toLocaleString() : '-';
}
