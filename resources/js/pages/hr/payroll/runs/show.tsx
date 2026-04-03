import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { ReasonDialog } from '@/components/feedback/reason-dialog';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type PayrollRun = {
    id: string;
    run_number: string;
    status: string;
    approver_name?: string | null;
    prepared_by_name?: string | null;
    approved_by_name?: string | null;
    posted_by_name?: string | null;
    decision_notes?: string | null;
    total_gross: number;
    total_deductions: number;
    total_reimbursements: number;
    total_net: number;
    prepared_at?: string | null;
    approved_at?: string | null;
    posted_at?: string | null;
    period: {
        name?: string | null;
        pay_frequency?: string | null;
        start_date?: string | null;
        end_date?: string | null;
        payment_date?: string | null;
    };
    journal_entry_number?: string | null;
    journal_entry_status?: string | null;
};

type WorkEntry = {
    id: string;
    employee_name?: string | null;
    employee_number?: string | null;
    entry_type: string;
    quantity: number;
    amount_reference?: number | null;
    status: string;
    conflict_reason?: string | null;
};

type Payslip = {
    id: string;
    payslip_number: string;
    employee_name?: string | null;
    employee_number?: string | null;
    gross_pay: number;
    total_deductions: number;
    reimbursement_amount: number;
    net_pay: number;
    line_count: number;
    status: string;
    can_view: boolean;
};

type ApprovalRequest = {
    id: string;
    status: string;
};

type Abilities = {
    can_prepare: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_post: boolean;
};

type Props = {
    run: PayrollRun;
    workEntries: WorkEntry[];
    payslips: Payslip[];
    approvalRequest?: ApprovalRequest | null;
    abilities: Abilities;
};

const labelize = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

export default function HrPayrollRunShow({
    run,
    workEntries,
    payslips,
    approvalRequest,
    abilities,
}: Props) {
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [postDialogOpen, setPostDialogOpen] = useState(false);
    const rejectForm = useForm({
        reason: '',
    });
    const postForm = useForm({});

    const openRejectDialog = () => {
        rejectForm.setData('reason', run.decision_notes ?? '');
        rejectForm.clearErrors();
        setRejectDialogOpen(true);
    };

    const closeRejectDialog = (open: boolean) => {
        if (rejectForm.processing) {
            return;
        }

        if (!open) {
            rejectForm.reset();
            rejectForm.clearErrors();
            setRejectDialogOpen(false);
        }
    };

    const rejectRun = () => {
        rejectForm.post(`/company/hr/payroll/runs/${run.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset();
                rejectForm.clearErrors();
                setRejectDialogOpen(false);
            },
        });
    };

    const closePostDialog = (open: boolean) => {
        if (postForm.processing) {
            return;
        }

        if (!open) {
            postForm.reset();
            postForm.clearErrors();
            setPostDialogOpen(false);
        }
    };

    const postPayroll = () => {
        postForm.post(`/company/hr/payroll/runs/${run.id}/post`, {
            preserveScroll: true,
            onSuccess: () => {
                postForm.reset();
                postForm.clearErrors();
                setPostDialogOpen(false);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={moduleBreadcrumbs(
                companyModuleLinks.hr,
                { title: 'Payroll', href: '/company/hr/payroll' },
                {
                    title: run.run_number,
                    href: `/company/hr/payroll/runs/${run.id}`,
                },
            )}
        >
            <Head title={run.run_number} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{run.run_number}</h1>
                        <p className="text-sm text-muted-foreground">
                            {run.period.name ?? 'No period'} | {labelize(run.status)}
                        </p>
                        {run.decision_notes ? (
                            <p className="mt-2 text-xs text-destructive">
                                Decision note: {run.decision_notes}
                            </p>
                        ) : null}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <BackLinkAction
                            href="/company/hr/payroll"
                            label="Back to payroll"
                            variant="outline"
                        />

                        {abilities.can_prepare && run.status === 'draft' ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/company/hr/payroll/runs/${run.id}/prepare`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Prepare run
                            </Button>
                        ) : null}

                        {abilities.can_approve && run.status === 'prepared' ? (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        `/company/hr/payroll/runs/${run.id}/approve`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Approve run
                            </Button>
                        ) : null}

                        {abilities.can_reject &&
                        (run.status === 'prepared' || run.status === 'approved') ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={openRejectDialog}
                            >
                                Reject run
                            </Button>
                        ) : null}

                        {abilities.can_post && run.status === 'approved' ? (
                            <Button
                                type="button"
                                onClick={() => setPostDialogOpen(true)}
                            >
                                Post payroll
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <Metric label="Gross" value={run.total_gross.toFixed(2)} />
                    <Metric
                        label="Deductions"
                        value={run.total_deductions.toFixed(2)}
                    />
                    <Metric
                        label="Reimb."
                        value={run.total_reimbursements.toFixed(2)}
                    />
                    <Metric label="Net" value={run.total_net.toFixed(2)} />
                    <Metric label="Entries" value={workEntries.length} />
                    <Metric label="Payslips" value={payslips.length} />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card title="Run details">
                        <Detail label="Period" value={run.period.name} />
                        <Detail label="Frequency" value={run.period.pay_frequency} />
                        <Detail
                            label="Range"
                            value={`${run.period.start_date ?? '-'} to ${run.period.end_date ?? '-'}`}
                        />
                        <Detail
                            label="Payment date"
                            value={run.period.payment_date}
                        />
                        <Detail label="Approver" value={run.approver_name} />
                        <Detail
                            label="Approval queue"
                            value={
                                approvalRequest
                                    ? labelize(approvalRequest.status)
                                    : 'Not queued'
                            }
                        />
                        <Detail label="Prepared by" value={run.prepared_by_name} />
                        <Detail label="Approved by" value={run.approved_by_name} />
                        <Detail label="Posted by" value={run.posted_by_name} />
                        <Detail
                            label="Journal entry"
                            value={
                                run.journal_entry_number
                                    ? `${run.journal_entry_number} | ${labelize(run.journal_entry_status ?? 'draft')}`
                                    : '-'
                            }
                        />
                        <Detail
                            label="Prepared at"
                            value={
                                run.prepared_at
                                    ? new Date(run.prepared_at).toLocaleString()
                                    : null
                            }
                        />
                        <Detail
                            label="Approved at"
                            value={
                                run.approved_at
                                    ? new Date(run.approved_at).toLocaleString()
                                    : null
                            }
                        />
                        <Detail
                            label="Posted at"
                            value={
                                run.posted_at
                                    ? new Date(run.posted_at).toLocaleString()
                                    : null
                            }
                        />
                    </Card>

                    <Card title="Status guidance">
                        <p className="text-sm text-muted-foreground">
                            Draft means the run exists but work entries and payslips
                            are not generated yet. Prepared means work entries and
                            payslips are ready for approval. Approved means finance
                            sign-off is complete. Posted means the accrual journal is
                            posted and payslips are published.
                        </p>
                    </Card>
                </div>

                <Card title="Work entries">
                    <Table
                        headers={[
                            'Employee',
                            'Type',
                            'Qty',
                            'Amount ref',
                            'Status',
                            'Conflict',
                        ]}
                        empty="Prepare the run to generate work entries."
                    >
                        {workEntries.map((entry) => (
                            <tr key={entry.id}>
                                <td className="px-3 py-2">
                                    <div>{entry.employee_name ?? '-'}</div>
                                    {entry.employee_number ? (
                                        <div className="text-xs text-muted-foreground">
                                            {entry.employee_number}
                                        </div>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">
                                    {labelize(entry.entry_type)}
                                </td>
                                <td className="px-3 py-2">
                                    {entry.quantity.toFixed(2)}
                                </td>
                                <td className="px-3 py-2">
                                    {entry.amount_reference != null
                                        ? entry.amount_reference.toFixed(2)
                                        : '-'}
                                </td>
                                <td className="px-3 py-2">
                                    {labelize(entry.status)}
                                </td>
                                <td className="px-3 py-2">
                                    {entry.conflict_reason ?? '-'}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>

                <Card title="Payslips">
                    <Table
                        headers={[
                            'Payslip',
                            'Employee',
                            'Gross',
                            'Deductions',
                            'Reimb.',
                            'Net',
                            'Lines',
                            'Status',
                            'Actions',
                        ]}
                        empty="No payslips generated for this run yet."
                    >
                        {payslips.map((payslip) => (
                            <tr key={payslip.id}>
                                <td className="px-3 py-2 font-medium">
                                    {payslip.payslip_number}
                                </td>
                                <td className="px-3 py-2">
                                    <div>{payslip.employee_name ?? '-'}</div>
                                    {payslip.employee_number ? (
                                        <div className="text-xs text-muted-foreground">
                                            {payslip.employee_number}
                                        </div>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">
                                    {payslip.gross_pay.toFixed(2)}
                                </td>
                                <td className="px-3 py-2">
                                    {payslip.total_deductions.toFixed(2)}
                                </td>
                                <td className="px-3 py-2">
                                    {payslip.reimbursement_amount.toFixed(2)}
                                </td>
                                <td className="px-3 py-2 font-medium">
                                    {payslip.net_pay.toFixed(2)}
                                </td>
                                <td className="px-3 py-2">{payslip.line_count}</td>
                                <td className="px-3 py-2">
                                    {labelize(payslip.status)}
                                </td>
                                <td className="px-3 py-2">
                                    {payslip.can_view ? (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link
                                                href={`/company/hr/payroll/payslips/${payslip.id}`}
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </Card>
            </div>

            <ReasonDialog
                open={rejectDialogOpen}
                onOpenChange={closeRejectDialog}
                title="Reject payroll run?"
                description="This keeps the run out of final posting so payroll can be corrected and resubmitted."
                confirmLabel="Reject run"
                processingLabel="Rejecting..."
                cancelLabel="Keep run"
                processing={rejectForm.processing}
                onConfirm={rejectRun}
                reason={rejectForm.data.reason}
                onReasonChange={(value) => rejectForm.setData('reason', value)}
                reasonLabel="Decision note"
                reasonPlaceholder="Optional note for why this payroll run is being rejected."
                reasonHelperText={`${run.run_number} | ${run.period.name ?? 'No period'} | ${labelize(run.status)}`}
                reasonError={rejectForm.errors.reason}
                errors={rejectForm.errors}
            />

            <ConfirmDialog
                open={postDialogOpen}
                onOpenChange={closePostDialog}
                title="Post payroll?"
                description="This will post the accrual journal and publish payslips for this payroll run."
                confirmLabel="Post payroll"
                processingLabel="Posting..."
                cancelLabel="Keep run approved"
                processing={postForm.processing}
                onConfirm={postPayroll}
                tone="warning"
                helperText={`${run.run_number} | ${run.period.name ?? 'No period'} | Net ${run.total_net.toFixed(2)}`}
            />
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function Detail({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-1">{value && value !== '' ? value : '-'}</p>
        </div>
    );
}

function Card({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border p-4">
            <h2 className="text-sm font-semibold">{title}</h2>
            <div className="mt-4 grid gap-3 text-sm md:grid-cols-2">
                {children}
            </div>
        </div>
    );
}

function Table({
    headers,
    empty,
    children,
}: {
    headers: string[];
    empty: string;
    children: React.ReactNode;
}) {
    const rows = Array.isArray(children) ? children : [children];

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-[900px] text-sm">
                <thead className="bg-muted/60 text-left">
                    <tr>
                        {headers.map((header) => (
                            <th key={header} className="px-3 py-2 font-medium">
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.filter(Boolean).length === 0 ? (
                        <tr>
                            <td
                                className="px-3 py-6 text-center text-muted-foreground"
                                colSpan={headers.length}
                            >
                                {empty}
                            </td>
                        </tr>
                    ) : (
                        children
                    )}
                </tbody>
            </table>
        </div>
    );
}
