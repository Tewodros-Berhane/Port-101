import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AccountingManualJournalLinesEditor, {
    type AccountingManualJournalLineInput,
} from '@/components/accounting/manual-journal-lines-editor';
import AttachmentsPanel from '@/components/attachments-panel';
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

type ManualJournalLine = AccountingManualJournalLineInput & {
    id?: string;
    account_code?: string | null;
    account_name?: string | null;
};

type Attachment = {
    id: string;
    original_name: string;
    mime_type?: string | null;
    size: number;
    created_at?: string | null;
    download_url: string;
};

type Props = {
    manualJournal: {
        id: string;
        entry_number: string;
        journal_id: string;
        journal_name?: string | null;
        status: string;
        requires_approval: boolean;
        approval_status: string;
        approval_requested_at?: string | null;
        approved_at?: string | null;
        rejected_at?: string | null;
        rejection_reason?: string | null;
        entry_date: string;
        reference?: string | null;
        description: string;
        posted_at?: string | null;
        reversed_at?: string | null;
        reversal_reason?: string | null;
        lines: ManualJournalLine[];
    };
    journals: JournalOption[];
    accounts: AccountOption[];
    attachments: Attachment[];
};

export default function AccountingManualJournalEdit({
    manualJournal,
    journals,
    accounts,
    attachments,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('accounting.manual_journals.manage');
    const canPost = hasPermission('accounting.manual_journals.post');
    const canViewAttachments = hasPermission('core.attachments.view');
    const canManageAttachments = hasPermission('core.attachments.manage');
    const isDraft = manualJournal.status === 'draft';
    const isPosted = manualJournal.status === 'posted';
    const approvalStateLabel = manualJournal.requires_approval
        ? manualJournal.approval_status.replace(/_/g, ' ')
        : 'not required';

    const form = useForm({
        journal_id: manualJournal.journal_id,
        entry_date: manualJournal.entry_date,
        reference: manualJournal.reference ?? '',
        description: manualJournal.description,
        lines: manualJournal.lines.map((line) => ({
            account_id: line.account_id,
            description: line.description,
            debit: line.debit,
            credit: line.credit,
        })),
    });

    const actionForm = useForm({});
    const deleteForm = useForm({});
    const reverseForm = useForm({
        reason: manualJournal.reversal_reason ?? '',
    });
    const [postDialogOpen, setPostDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const totals = calculateTotals(form.data.lines);

    const closePostDialog = (open: boolean) => {
        if (actionForm.processing) {
            return;
        }

        if (!open) {
            setPostDialogOpen(false);
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
        actionForm.post(
            `/company/accounting/manual-journals/${manualJournal.id}/post`,
            {
                preserveScroll: true,
                onSuccess: () => setPostDialogOpen(false),
            },
        );
    };

    const submitDelete = () => {
        deleteForm.delete(
            `/company/accounting/manual-journals/${manualJournal.id}`,
            {
                onSuccess: () => setDeleteDialogOpen(false),
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.accounting, {
                    title: 'Manual Journals',
                    href: '/company/accounting/manual-journals',
                },
                {
                    title: manualJournal.entry_number,
                    href: `/company/accounting/manual-journals/${manualJournal.id}/edit`,
                },)}
        >
            <Head title={manualJournal.entry_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">
                        Edit manual journal
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {manualJournal.entry_number} - {manualJournal.status}
                    </p>
                </div>
                <BackLinkAction href="/company/accounting/manual-journals" label="Back to manual journals" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(
                        `/company/accounting/manual-journals/${manualJournal.id}`,
                    );
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
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
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
                            disabled={!isDraft || form.processing}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </div>

                <AccountingManualJournalLinesEditor
                    lines={form.data.lines}
                    accounts={accounts}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={!isDraft || form.processing}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-2 xl:grid-cols-5">
                    <div>
                        <p className="text-xs text-muted-foreground">Status</p>
                        <p className="font-semibold capitalize">
                            {manualJournal.status}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Approval
                        </p>
                        <p className="font-semibold capitalize">
                            {approvalStateLabel}
                        </p>
                    </div>
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

                {manualJournal.requires_approval && (
                    <div className="rounded-xl border p-4 text-sm">
                        <h2 className="text-sm font-semibold">
                            Approval state
                        </h2>
                        <p className="mt-1 capitalize text-muted-foreground">
                            {approvalStateLabel}
                        </p>
                        {manualJournal.approval_requested_at && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Requested:{' '}
                                {formatDateTime(
                                    manualJournal.approval_requested_at,
                                )}
                            </p>
                        )}
                        {manualJournal.approved_at && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                Approved:{' '}
                                {formatDateTime(manualJournal.approved_at)}
                            </p>
                        )}
                        {manualJournal.rejected_at && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                Rejected:{' '}
                                {formatDateTime(manualJournal.rejected_at)}
                            </p>
                        )}
                        {manualJournal.rejection_reason && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Reason: {manualJournal.rejection_reason}
                            </p>
                        )}
                    </div>
                )}

                <AttachmentsPanel
                    attachableType="manual_journal"
                    attachableId={manualJournal.id}
                    attachments={attachments}
                    canView={canViewAttachments}
                    canManage={canManageAttachments}
                />

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && isDraft && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canPost && isDraft && (
                        <Button
                            type="button"
                            onClick={() => setPostDialogOpen(true)}
                            disabled={
                                actionForm.processing ||
                                (manualJournal.requires_approval &&
                                    manualJournal.approval_status !==
                                        'approved')
                            }
                        >
                            Post journal
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
                title="Post journal?"
                description={
                    <>
                        Post <span className="font-medium">{manualJournal.entry_number}</span>{' '}
                        into the general ledger.
                    </>
                }
                confirmLabel="Post journal"
                processingLabel="Posting..."
                helperText="Use this when the journal is balanced, reviewed, and ready to affect accounting balances."
                onConfirm={submitPost}
                processing={actionForm.processing}
            />

            <DestructiveConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={closeDeleteDialog}
                title="Delete journal?"
                description={
                    <>
                        Delete <span className="font-medium">{manualJournal.entry_number}</span>{' '}
                        and remove its current draft lines.
                    </>
                }
                confirmLabel="Delete journal"
                processingLabel="Deleting..."
                helperText="This should only be used for draft journals that should be removed entirely."
                onConfirm={submitDelete}
                processing={deleteForm.processing}
            />

            {canPost && isPosted && (
                <div className="mt-6 rounded-xl border p-4">
                    <h2 className="text-sm font-semibold">Reverse journal</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Create an explicit reversal batch for this entry.
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
                                    `/company/accounting/manual-journals/${manualJournal.id}/reverse`,
                                )
                            }
                            disabled={reverseForm.processing}
                        >
                            Reverse journal
                        </Button>
                    </div>
                </div>
            )}
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

function formatDateTime(value?: string | null) {
    return value ? new Date(value).toLocaleString() : '-';
}
