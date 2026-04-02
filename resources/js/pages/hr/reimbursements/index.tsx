import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ModalFormShell } from '@/components/modals/modal-form-shell';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { FormErrorSummary } from '@/components/shell/form-error-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFeedbackToast } from '@/hooks/use-feedback-toast';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Summary = {
    open_claims: number;
    pending_my_approvals: number;
    approved_30d: number;
    posted_unpaid: number;
    paid_30d_amount: number;
    categories: number;
};

type EmployeeOption = { id: string; name: string; employee_number?: string | null };

type CategoryRow = {
    id: string;
    name: string;
    code?: string | null;
    requires_receipt: boolean;
    is_project_rebillable: boolean;
    default_expense_account_reference?: string | null;
    can_edit: boolean;
};

type ClaimRow = {
    id: string;
    claim_number: string;
    status: string;
    employee_name?: string | null;
    employee_number?: string | null;
    approver_name?: string | null;
    approved_by_name?: string | null;
    rejected_by_name?: string | null;
    total_amount: number;
    line_count: number;
    missing_required_receipts: number;
    decision_notes?: string | null;
    invoice_number?: string | null;
    invoice_status?: string | null;
    payment_number?: string | null;
    payment_status?: string | null;
    submitted_at?: string | null;
    approved_at?: string | null;
    rejected_at?: string | null;
    can_edit: boolean;
    can_submit: boolean;
    can_approve: boolean;
    can_reject: boolean;
    can_post: boolean;
    can_pay: boolean;
};

type Props = {
    summary: Summary;
    filters: { status: string; employee_id: string };
    statuses: string[];
    employeeOptions: EmployeeOption[];
    linkedEmployeeId?: string | null;
    categories: CategoryRow[];
    claims: {
        data: ClaimRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    abilities: {
        can_create_claim: boolean;
        can_manage_categories: boolean;
        can_approve_claims: boolean;
    };
};

const labelize = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const CATEGORY_ERROR_LABELS: Record<string, string> = {
    default_expense_account_reference: 'Default expense account reference',
    is_project_rebillable: 'Allow project rebilling',
    requires_receipt: 'Receipt required',
};

export default function HrReimbursementsIndex({
    summary,
    filters,
    statuses,
    employeeOptions,
    linkedEmployeeId,
    categories,
    claims,
    abilities,
}: Props) {
    const form = useForm(filters);
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const { clientToastHeaders, showPageFlashToast } = useFeedbackToast();
    const categoryForm = useForm({
        name: '',
        code: '',
        default_expense_account_reference: '',
        requires_receipt: false,
        is_project_rebillable: false,
    });

    const closeCategoryModal = (open: boolean) => {
        setShowCategoryModal(open);

        if (!open) {
            categoryForm.reset();
            categoryForm.clearErrors();
        }
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.hr, { title: 'Reimbursements', href: '/company/hr/reimbursements' },)}
        >
            <Head title="Reimbursements workspace" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Reimbursements workspace</h1>
                        <p className="text-sm text-muted-foreground">
                            Expense claims, receipt compliance, two-stage approvals, and accounting handoff.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <BackLinkAction href="/company/hr" label="Back to HR" variant="outline" />
                        {abilities.can_manage_categories && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowCategoryModal(true)}
                            >
                                New category
                            </Button>
                        )}
                        {abilities.can_create_claim && (
                            <Button asChild>
                                <Link href="/company/hr/reimbursements/claims/create">New claim</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <ModalFormShell
                    open={showCategoryModal}
                    onOpenChange={closeCategoryModal}
                    title="New reimbursement category"
                    description="Create expense categories for travel, meals, subscriptions, and other reimbursable costs."
                    className="sm:max-w-xl"
                >
                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            categoryForm.post(
                                '/company/hr/reimbursements/categories',
                                {
                                    headers: clientToastHeaders,
                                    onSuccess: (page) => {
                                        showPageFlashToast(page);
                                        closeCategoryModal(false);
                                    },
                                },
                            );
                        }}
                    >
                        <FormErrorSummary
                            errors={categoryForm.errors}
                            fieldLabels={CATEGORY_ERROR_LABELS}
                            title="Review the category details before saving."
                        />
                        <Field label="Name" error={categoryForm.errors.name}>
                            <Input
                                value={categoryForm.data.name}
                                onChange={(event) =>
                                    categoryForm.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Code" error={categoryForm.errors.code}>
                            <Input
                                value={categoryForm.data.code}
                                onChange={(event) =>
                                    categoryForm.setData(
                                        'code',
                                        event.target.value,
                                    )
                                }
                                placeholder="Optional short code"
                            />
                        </Field>
                        <Field
                            label="Default expense account reference"
                            error={
                                categoryForm.errors
                                    .default_expense_account_reference
                            }
                        >
                            <Input
                                value={
                                    categoryForm.data
                                        .default_expense_account_reference
                                }
                                onChange={(event) =>
                                    categoryForm.setData(
                                        'default_expense_account_reference',
                                        event.target.value,
                                    )
                                }
                                placeholder="Optional accounting reference"
                            />
                        </Field>
                        <label className="flex items-center gap-2 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3 text-sm">
                            <input
                                type="checkbox"
                                checked={categoryForm.data.requires_receipt}
                                onChange={(event) =>
                                    categoryForm.setData(
                                        'requires_receipt',
                                        event.target.checked,
                                    )
                                }
                            />
                            Receipt required
                        </label>
                        <label className="flex items-center gap-2 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3 text-sm">
                            <input
                                type="checkbox"
                                checked={
                                    categoryForm.data.is_project_rebillable
                                }
                                onChange={(event) =>
                                    categoryForm.setData(
                                        'is_project_rebillable',
                                        event.target.checked,
                                    )
                                }
                            />
                            Allow project rebilling
                        </label>
                        <div className="flex items-center justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeCategoryModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={categoryForm.processing}>
                                Create category
                            </Button>
                        </div>
                    </form>
                </ModalFormShell>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <Metric label="Open claims" value={summary.open_claims} />
                    <Metric label="My approvals" value={summary.pending_my_approvals} />
                    <Metric label="Approved 30d" value={summary.approved_30d} />
                    <Metric label="Posted unpaid" value={summary.posted_unpaid} />
                    <Metric label="Paid 30d" value={summary.paid_30d_amount.toFixed(2)} />
                    <Metric label="Categories" value={summary.categories} />
                </div>

                <form
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-3 xl:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get('/company/hr/reimbursements', { preserveState: true, replace: true });
                    }}
                >
                    <Field label="Status" error={form.errors.status}>
                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.status}
                            onChange={(event) => form.setData('status', event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {labelize(status)}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Employee" error={form.errors.employee_id}>
                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.employee_id}
                            onChange={(event) => form.setData('employee_id', event.target.value)}
                        >
                            <option value="">All employees</option>
                            {employeeOptions.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name}
                                    {employee.employee_number ? ` (${employee.employee_number})` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Apply</Button>
                        <Button type="button" variant="ghost" onClick={() => router.get('/company/hr/reimbursements')}>
                            Reset
                        </Button>
                        {linkedEmployeeId && !form.data.employee_id && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.get('/company/hr/reimbursements', { ...filters, employee_id: linkedEmployeeId })
                                }
                            >
                                My claims
                            </Button>
                        )}
                    </div>
                </form>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <h2 className="text-sm font-semibold">Claims</h2>
                            <p className="text-xs text-muted-foreground">
                                Drafts, approvals, receipts, and accounting state in one queue.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[1300px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Claim</th>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Approver</th>
                                    <th className="px-3 py-2 font-medium">Lines</th>
                                    <th className="px-3 py-2 font-medium">Amount</th>
                                    <th className="px-3 py-2 font-medium">Invoice</th>
                                    <th className="px-3 py-2 font-medium">Payment</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {claims.data.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-center text-muted-foreground" colSpan={9}>
                                            No reimbursement claims found.
                                        </td>
                                    </tr>
                                )}
                                {claims.data.map((claim) => (
                                    <tr key={claim.id}>
                                        <td className="px-3 py-2 align-top">
                                            <div className="font-medium">{claim.claim_number}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {claim.submitted_at ? new Date(claim.submitted_at).toLocaleString() : 'Not submitted'}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div>{claim.employee_name ?? '-'}</div>
                                            {claim.employee_number && (
                                                <div className="text-xs text-muted-foreground">{claim.employee_number}</div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            {claim.approver_name ?? claim.approved_by_name ?? claim.rejected_by_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div>{claim.line_count} lines</div>
                                            {claim.missing_required_receipts > 0 && (
                                                <div className="text-xs text-amber-600">
                                                    {claim.missing_required_receipts} missing required receipts
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top font-medium">{claim.total_amount.toFixed(2)}</td>
                                        <td className="px-3 py-2 align-top">
                                            {claim.invoice_number ?? '-'}
                                            {claim.invoice_status && (
                                                <div className="text-xs text-muted-foreground">
                                                    {labelize(claim.invoice_status)}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            {claim.payment_number ?? '-'}
                                            {claim.payment_status && (
                                                <div className="text-xs text-muted-foreground">
                                                    {labelize(claim.payment_status)}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div>{labelize(claim.status)}</div>
                                            {claim.decision_notes && (
                                                <div
                                                    className="mt-1 max-w-xs truncate text-xs text-muted-foreground"
                                                    title={claim.decision_notes}
                                                >
                                                    {claim.decision_notes}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <div className="flex flex-wrap gap-2">
                                                {claim.can_edit && (
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/company/hr/reimbursements/claims/${claim.id}/edit`}>Edit</Link>
                                                    </Button>
                                                )}
                                                {claim.can_submit && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/hr/reimbursements/claims/${claim.id}/submit`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Submit
                                                    </Button>
                                                )}
                                                {claim.can_approve && (
                                                    <Button
                                                        size="sm"
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/hr/reimbursements/claims/${claim.id}/approve`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </Button>
                                                )}
                                                {claim.can_reject && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        type="button"
                                                        onClick={() => {
                                                            const reason = window.prompt(
                                                                'Rejection reason (optional)',
                                                                '',
                                                            );

                                                            if (reason === null) {
                                                                return;
                                                            }

                                                            router.post(
                                                                `/company/hr/reimbursements/claims/${claim.id}/reject`,
                                                                { reason },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Reject
                                                    </Button>
                                                )}
                                                {claim.can_post && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/hr/reimbursements/claims/${claim.id}/post`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Post to accounting
                                                    </Button>
                                                )}
                                                {claim.can_pay && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/company/hr/reimbursements/claims/${claim.id}/pay`,
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        Mark paid
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {claims.links.length > 3 && (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {claims.links.map((link, index) => (
                                <Button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    variant={link.active ? 'default' : 'outline'}
                                    disabled={!link.url}
                                    onClick={() =>
                                        link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })
                                    }
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Expense categories</h2>
                        {abilities.can_manage_categories && (
                            <Button variant="ghost" asChild>
                                <Link href="/company/hr/reimbursements/categories/create">New category</Link>
                            </Button>
                        )}
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[860px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Name</th>
                                    <th className="px-3 py-2 font-medium">Code</th>
                                    <th className="px-3 py-2 font-medium">Receipt</th>
                                    <th className="px-3 py-2 font-medium">Rebillable</th>
                                    <th className="px-3 py-2 font-medium">Account reference</th>
                                    <th className="px-3 py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {categories.length === 0 && (
                                    <tr>
                                        <td className="px-3 py-6 text-center text-muted-foreground" colSpan={6}>
                                            No reimbursement categories created yet.
                                        </td>
                                    </tr>
                                )}
                                {categories.map((category) => (
                                    <tr key={category.id}>
                                        <td className="px-3 py-2 font-medium">{category.name}</td>
                                        <td className="px-3 py-2">{category.code ?? '-'}</td>
                                        <td className="px-3 py-2">
                                            {category.requires_receipt ? 'Required' : 'Optional'}
                                        </td>
                                        <td className="px-3 py-2">{category.is_project_rebillable ? 'Yes' : 'No'}</td>
                                        <td className="px-3 py-2">{category.default_expense_account_reference ?? '-'}</td>
                                        <td className="px-3 py-2">
                                            {category.can_edit && (
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/company/hr/reimbursements/categories/${category.id}/edit`}>Edit</Link>
                                                </Button>
                                            )}
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

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
