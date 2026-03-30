import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type EmployeeOption = { id: string; name: string; employee_number?: string | null };
type CategoryOption = {
    id: string;
    name: string;
    code?: string | null;
    requires_receipt: boolean;
    is_project_rebillable: boolean;
};
type ProjectOption = { id: string; project_code: string; name: string };
type CurrencyOption = { id: string; code: string; name: string; symbol?: string | null };
type ReceiptAttachment = { id: string; original_name: string; mime_type?: string | null; size: number };

type ClaimLine = {
    id: string;
    category_id: string;
    expense_date: string;
    description: string;
    amount: string;
    tax_amount: string;
    project_id: string;
    category_name?: string | null;
    requires_receipt?: boolean;
    receipt_attachment?: ReceiptAttachment | null;
};

type ClaimFormData = {
    employee_id: string;
    currency_id: string;
    project_id: string;
    notes: string;
    action: string;
    lines: ClaimLine[];
};

type Props = {
    mode: 'create' | 'edit';
    title: string;
    description: string;
    submitUrl: string;
    method: 'post' | 'put';
    backHref: string;
    employeeOptions: EmployeeOption[];
    categoryOptions: CategoryOption[];
    projectOptions: ProjectOption[];
    currencyOptions: CurrencyOption[];
    form: ClaimFormData;
    claimMeta?: {
        id: string;
        claim_number: string;
        status: string;
        decision_notes?: string | null;
    };
};

const labelize = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const emptyLine = (): ClaimLine => ({
    id: '',
    category_id: '',
    expense_date: new Date().toISOString().slice(0, 10),
    description: '',
    amount: '',
    tax_amount: '0',
    project_id: '',
});

export default function ClaimForm({
    mode,
    title,
    description,
    submitUrl,
    method,
    backHref,
    employeeOptions,
    categoryOptions,
    projectOptions,
    currencyOptions,
    form: initialForm,
    claimMeta,
}: Props) {
    const form = useForm<ClaimFormData>(initialForm);
    const [receiptFiles, setReceiptFiles] = useState<Record<string, File | null>>({});

    const updateLine = (index: number, field: keyof ClaimLine, value: string) => {
        const nextLines = [...form.data.lines];
        nextLines[index] = { ...nextLines[index], [field]: value };
        form.setData('lines', nextLines);
    };

    const submit = (action: 'draft' | 'save' | 'submit') => {
        const payload = { ...form.data, action };

        if (method === 'put') {
            form.transform(() => payload).put(submitUrl);
            return;
        }

        form.transform(() => payload).post(submitUrl);
    };

    const uploadReceipt = (lineId: string) => {
        const file = receiptFiles[lineId];

        if (!file) {
            return;
        }

        router.post(
            `/company/hr/reimbursements/lines/${lineId}/receipt`,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setReceiptFiles((current) => ({ ...current, [lineId]: null }));
                },
            },
        );
    };

    const saveLabel = mode === 'create' ? 'Save draft' : 'Save changes';
    const saveAction = mode === 'create' ? 'draft' : 'save';

    return (
        <AppLayout
            breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Reimbursements', href: '/company/hr/reimbursements' },
                { title, href: backHref },)}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">{description}</p>
                    {claimMeta && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            {claimMeta.claim_number} · {labelize(claimMeta.status)}
                        </p>
                    )}
                    {claimMeta?.decision_notes && (
                        <p className="mt-2 max-w-3xl text-xs text-destructive">
                            Last decision note: {claimMeta.decision_notes}
                        </p>
                    )}
                </div>
                <BackLinkAction href="/company/hr/reimbursements" label="Back to reimbursements" variant="ghost" />
            </div>

            <form
                className="mt-6 space-y-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit('submit');
                }}
            >
                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <Field label="Employee" error={form.errors.employee_id}>
                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.employee_id}
                            onChange={(event) => form.setData('employee_id', event.target.value)}
                        >
                            <option value="">Use my linked employee</option>
                            {employeeOptions.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.name}
                                    {employee.employee_number ? ` (${employee.employee_number})` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Currency" error={form.errors.currency_id}>
                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.currency_id}
                            onChange={(event) => form.setData('currency_id', event.target.value)}
                        >
                            <option value="">Use company currency</option>
                            {currencyOptions.map((currency) => (
                                <option key={currency.id} value={currency.id}>
                                    {currency.code} · {currency.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Project" error={form.errors.project_id}>
                        <select
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.project_id}
                            onChange={(event) => form.setData('project_id', event.target.value)}
                        >
                            <option value="">No default project</option>
                            {projectOptions.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.project_code} · {project.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div className="rounded-lg border px-3 py-3 text-xs text-muted-foreground">
                        Save reimbursement claims as a draft first if any line requires a receipt.
                        Upload the receipts on the edit screen, then submit.
                    </div>
                </div>

                <Field label="Notes" error={form.errors.notes}>
                    <textarea
                        className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                    />
                </Field>

                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold">Claim lines</h2>
                            <p className="text-xs text-muted-foreground">
                                Capture each reimbursable expense as a separate line.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => form.setData('lines', [...form.data.lines, emptyLine()])}
                        >
                            Add line
                        </Button>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[1220px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Category</th>
                                    <th className="px-3 py-2 font-medium">Expense date</th>
                                    <th className="px-3 py-2 font-medium">Description</th>
                                    <th className="px-3 py-2 font-medium">Amount</th>
                                    <th className="px-3 py-2 font-medium">Tax</th>
                                    <th className="px-3 py-2 font-medium">Project</th>
                                    <th className="px-3 py-2 font-medium">Receipt</th>
                                    <th className="px-3 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {form.data.lines.map((line, index) => (
                                    <tr key={line.id || `new-${index}`}>
                                        <td className="px-3 py-2 align-top">
                                            <select
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                                                value={line.category_id}
                                                onChange={(event) => updateLine(index, 'category_id', event.target.value)}
                                            >
                                                <option value="">Select category</option>
                                                {categoryOptions.map((category) => (
                                                    <option key={category.id} value={category.id}>
                                                        {category.name}
                                                        {category.requires_receipt ? ' · receipt' : ''}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.category_id` as keyof typeof form.errors
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <Input
                                                type="date"
                                                value={line.expense_date}
                                                onChange={(event) =>
                                                    updateLine(index, 'expense_date', event.target.value)
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.expense_date` as keyof typeof form.errors
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <Input
                                                value={line.description}
                                                onChange={(event) =>
                                                    updateLine(index, 'description', event.target.value)
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.description` as keyof typeof form.errors
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <Input
                                                value={line.amount}
                                                onChange={(event) => updateLine(index, 'amount', event.target.value)}
                                                placeholder="0.00"
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.amount` as keyof typeof form.errors
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <Input
                                                value={line.tax_amount}
                                                onChange={(event) =>
                                                    updateLine(index, 'tax_amount', event.target.value)
                                                }
                                                placeholder="0.00"
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.tax_amount` as keyof typeof form.errors
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            <select
                                                className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                                                value={line.project_id}
                                                onChange={(event) => updateLine(index, 'project_id', event.target.value)}
                                            >
                                                <option value="">No project</option>
                                                {projectOptions.map((project) => (
                                                    <option key={project.id} value={project.id}>
                                                        {project.project_code}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${index}.project_id` as keyof typeof form.errors
                                                    ]
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 align-top">
                                            {mode === 'edit' && line.id ? (
                                                <div className="space-y-2">
                                                    {line.receipt_attachment ? (
                                                        <div className="rounded-md border px-3 py-2 text-xs">
                                                            <p className="font-medium">
                                                                {line.receipt_attachment.original_name}
                                                            </p>
                                                            <div className="mt-2 flex flex-wrap gap-2">
                                                                <Button type="button" variant="outline" size="sm" asChild>
                                                                    <Link
                                                                        href={`/company/hr/reimbursements/lines/${line.id}/receipt/download`}
                                                                    >
                                                                        Download
                                                                    </Link>
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        router.delete(
                                                                            `/company/hr/reimbursements/lines/${line.id}/receipt`,
                                                                            { preserveScroll: true },
                                                                        )
                                                                    }
                                                                >
                                                                    Remove
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <p className="text-xs text-muted-foreground">
                                                            No receipt uploaded.
                                                        </p>
                                                    )}
                                                    <input
                                                        type="file"
                                                        className="block w-full text-xs"
                                                        onChange={(event) =>
                                                            setReceiptFiles((current) => ({
                                                                ...current,
                                                                [line.id]: event.target.files?.[0] ?? null,
                                                            }))
                                                        }
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={!receiptFiles[line.id]}
                                                        onClick={() => uploadReceipt(line.id)}
                                                    >
                                                        Upload receipt
                                                    </Button>
                                                </div>
                                            ) : (
                                                <p className="max-w-[180px] text-xs text-muted-foreground">
                                                    Save this claim first to attach a receipt.
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right align-top">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.filter((_, lineIndex) => lineIndex !== index),
                                                    )
                                                }
                                                disabled={form.data.lines.length === 1}
                                            >
                                                Remove
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => submit(saveAction)}
                    >
                        {saveLabel}
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Submit for approval
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
