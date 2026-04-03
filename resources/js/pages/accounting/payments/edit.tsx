import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { DestructiveConfirmDialog } from '@/components/feedback/destructive-confirm-dialog';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type InvoiceOption = {
    id: string;
    invoice_number: string;
    partner_name?: string | null;
    balance_due: number;
};

type Reconciliation = {
    id: string;
    entry_type: string;
    amount: number;
    reconciled_at?: string | null;
};

type Payment = {
    id: string;
    invoice_id: string;
    invoice_number?: string | null;
    invoice_status?: string | null;
    partner_name?: string | null;
    payment_number: string;
    status: string;
    payment_date: string;
    amount: number;
    method?: string | null;
    reference?: string | null;
    notes?: string | null;
    posted_at?: string | null;
    reconciled_at?: string | null;
    bank_reconciled_at?: string | null;
    reversed_at?: string | null;
    reversal_reason?: string | null;
    reconciliations: Reconciliation[];
};

type Props = {
    payment: Payment;
    invoices: InvoiceOption[];
};

export default function AccountingPaymentEdit({ payment, invoices }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('accounting.payments.manage');
    const canReverse = hasPermission('accounting.payments.approve_reversal');
    const isDraft = payment.status === 'draft';
    const isPosted = payment.status === 'posted';

    const form = useForm({
        invoice_id: payment.invoice_id,
        payment_date: payment.payment_date,
        amount: payment.amount,
        method: payment.method ?? '',
        reference: payment.reference ?? '',
        notes: payment.notes ?? '',
    });

    const actionForm = useForm({});
    const deleteForm = useForm({});
    const reverseForm = useForm({
        reason: payment.reversal_reason ?? '',
    });
    const [postDialogOpen, setPostDialogOpen] = useState(false);
    const [reconcileDialogOpen, setReconcileDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const closePostDialog = (open: boolean) => {
        if (actionForm.processing) {
            return;
        }

        if (!open) {
            setPostDialogOpen(false);
        }
    };

    const closeReconcileDialog = (open: boolean) => {
        if (actionForm.processing) {
            return;
        }

        if (!open) {
            setReconcileDialogOpen(false);
        }
    };

    const closeDeleteDialog = (open: boolean) => {
        if (deleteForm.processing) {
            return;
        }

        if (!open) {
            setDeleteDialogOpen(false);
        }
    };

    const submitPost = () => {
        actionForm.post(`/company/accounting/payments/${payment.id}/post`, {
            preserveScroll: true,
            onSuccess: () => setPostDialogOpen(false),
        });
    };

    const submitReconcile = () => {
        actionForm.post(
            `/company/accounting/payments/${payment.id}/reconcile`,
            {
                preserveScroll: true,
                onSuccess: () => setReconcileDialogOpen(false),
            },
        );
    };

    const submitDelete = () => {
        deleteForm.delete(`/company/accounting/payments/${payment.id}`, {
            onSuccess: () => setDeleteDialogOpen(false),
        });
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, { title: 'Payments', href: '/company/accounting/payments' },
                {
                    title: payment.payment_number,
                    href: `/company/accounting/payments/${payment.id}/edit`,
                },)}
        >
            <Head title={payment.payment_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit payment</h1>
                    <p className="text-sm text-muted-foreground">
                        {payment.payment_number} - {payment.status}
                    </p>
                </div>
                <BackLinkAction href="/company/accounting/payments" label="Back to payments" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/accounting/payments/${payment.id}`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="grid gap-2 xl:col-span-2">
                        <Label htmlFor="invoice_id">Invoice</Label>
                        <select
                            id="invoice_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.invoice_id}
                            onChange={(event) =>
                                form.setData('invoice_id', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        >
                            <option value="">Select invoice</option>
                            {invoices.map((invoice) => (
                                <option key={invoice.id} value={invoice.id}>
                                    {invoice.invoice_number}
                                    {invoice.partner_name
                                        ? ` - ${invoice.partner_name}`
                                        : ''}
                                    {` (Balance ${invoice.balance_due.toFixed(2)})`}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.invoice_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payment_date">Payment date</Label>
                        <Input
                            id="payment_date"
                            type="date"
                            value={form.data.payment_date}
                            onChange={(event) =>
                                form.setData('payment_date', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.payment_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount</Label>
                        <Input
                            id="amount"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData(
                                    'amount',
                                    Number(event.target.value) || 0,
                                )
                            }
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="method">Method</Label>
                        <Input
                            id="method"
                            value={form.data.method}
                            onChange={(event) =>
                                form.setData('method', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.method} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="reference">Reference</Label>
                        <Input
                            id="reference"
                            value={form.data.reference}
                            onChange={(event) =>
                                form.setData('reference', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.reference} />
                    </div>

                    <div className="grid gap-2 xl:col-span-3">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-4">
                    <div>
                        <p className="text-xs text-muted-foreground">Invoice</p>
                        <p className="font-semibold">
                            {payment.invoice_number ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Partner</p>
                        <p className="font-semibold">
                            {payment.partner_name ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Status</p>
                        <p className="font-semibold capitalize">
                            {payment.status}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Bank reconciliation
                        </p>
                        <p className="font-semibold">
                            {payment.bank_reconciled_at ?? 'Pending'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Amount</p>
                        <p className="font-semibold">
                            {payment.amount.toFixed(2)}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && isDraft && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canManage && isDraft && (
                        <Button
                            type="button"
                            onClick={() => setPostDialogOpen(true)}
                            disabled={actionForm.processing}
                        >
                            Post payment
                        </Button>
                    )}

                    {canManage && isPosted && (
                        <Button
                            type="button"
                            onClick={() => setReconcileDialogOpen(true)}
                            disabled={actionForm.processing}
                        >
                            Reconcile payment
                        </Button>
                    )}

                    {canManage && isDraft && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => setDeleteDialogOpen(true)}
                            disabled={deleteForm.processing}
                        >
                            Delete
                        </Button>
                    )}
                </div>
            </form>

            <ConfirmDialog
                open={postDialogOpen}
                onOpenChange={closePostDialog}
                tone="warning"
                title="Post payment?"
                description={
                    <>
                        Post <span className="font-medium">{payment.payment_number}</span>{' '}
                        and move it into the accounting payment workflow.
                    </>
                }
                confirmLabel="Post payment"
                processingLabel="Posting..."
                helperText="Only post the payment once the amount, date, and reference are final."
                onConfirm={submitPost}
                processing={actionForm.processing}
            />

            <ConfirmDialog
                open={reconcileDialogOpen}
                onOpenChange={closeReconcileDialog}
                tone="warning"
                title="Reconcile payment?"
                description={
                    <>
                        Reconcile <span className="font-medium">{payment.payment_number}</span>{' '}
                        against its invoice balance.
                    </>
                }
                confirmLabel="Reconcile payment"
                processingLabel="Reconciling..."
                helperText="Use this when the payment should be marked as applied in the ledger."
                onConfirm={submitReconcile}
                processing={actionForm.processing}
            />

            <DestructiveConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={closeDeleteDialog}
                title="Delete payment?"
                description={
                    <>
                        Delete <span className="font-medium">{payment.payment_number}</span> from
                        the draft payment queue.
                    </>
                }
                confirmLabel="Delete payment"
                processingLabel="Deleting..."
                helperText="This should only be used for draft payments that should be removed entirely."
                onConfirm={submitDelete}
                processing={deleteForm.processing}
            />

            {canReverse &&
                !payment.bank_reconciled_at &&
                (payment.status === 'draft' ||
                    payment.status === 'posted' ||
                    payment.status === 'reconciled') && (
                    <div className="mt-6 rounded-xl border p-4">
                        <h2 className="text-sm font-semibold">
                            Reverse payment
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Provide a reason for audit traceability.
                        </p>
                        <div className="mt-3 grid gap-2">
                            <Label htmlFor="reverse_reason">Reason</Label>
                            <textarea
                                id="reverse_reason"
                                className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={reverseForm.data.reason}
                                onChange={(event) =>
                                    reverseForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={reverseForm.errors.reason} />
                        </div>
                        <div className="mt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    reverseForm.post(
                                        `/company/accounting/payments/${payment.id}/reverse`,
                                    )
                                }
                                disabled={reverseForm.processing}
                            >
                                Reverse payment
                            </Button>
                        </div>
                    </div>
                )}

            {payment.bank_reconciled_at && (
                <div className="mt-6 rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">
                        Bank reconciliation locked
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        This payment is already matched to a bank statement and
                        cannot be reversed until an explicit unreconcile
                        workflow exists.
                    </p>
                    <div className="mt-3">
                        <Button variant="outline" asChild>
                            <Link href="/company/accounting/bank-reconciliation">
                                Open bank reconciliation
                            </Link>
                        </Button>
                    </div>
                </div>
            )}

            <div className="mt-6 rounded-xl border p-4">
                <h2 className="text-sm font-semibold">Reconciliation ledger</h2>
                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[620px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Amount</th>
                                <th className="px-3 py-2 font-medium">
                                    Reconciled at
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {payment.reconciliations.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-6 text-center text-muted-foreground"
                                        colSpan={3}
                                    >
                                        No reconciliation entries yet.
                                    </td>
                                </tr>
                            )}
                            {payment.reconciliations.map((entry) => (
                                <tr key={entry.id}>
                                    <td className="px-3 py-2 capitalize">
                                        {entry.entry_type}
                                    </td>
                                    <td className="px-3 py-2">
                                        {entry.amount.toFixed(2)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {entry.reconciled_at ?? '-'}
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
