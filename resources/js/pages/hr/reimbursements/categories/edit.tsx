import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Props = {
    category: {
        id: string;
        name: string;
        code: string;
        default_expense_account_reference: string;
        requires_receipt: boolean;
        is_project_rebillable: boolean;
    };
};

export default function HrReimbursementCategoryEdit({ category }: Props) {
    const form = useForm(category);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'HR', href: '/company/hr' },
                { title: 'Reimbursements', href: '/company/hr/reimbursements' },
                { title: 'Edit category', href: `/company/hr/reimbursements/categories/${category.id}/edit` },
            ]}
        >
            <Head title={`Edit ${category.name}`} />

            <div className="flex items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit reimbursement category</h1>
                    <p className="text-sm text-muted-foreground">
                        Update receipt requirements and default accounting references for this expense category.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/hr/reimbursements">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6 md:max-w-2xl"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/hr/reimbursements/categories/${category.id}`);
                }}
            >
                <Field label="Name" error={form.errors.name}>
                    <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </Field>
                <Field label="Code" error={form.errors.code}>
                    <Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />
                </Field>
                <Field label="Default expense account reference" error={form.errors.default_expense_account_reference}>
                    <Input
                        value={form.data.default_expense_account_reference}
                        onChange={(event) =>
                            form.setData('default_expense_account_reference', event.target.value)
                        }
                    />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.requires_receipt}
                        onChange={(event) => form.setData('requires_receipt', event.target.checked)}
                    />
                    Receipt required
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_project_rebillable}
                        onChange={(event) => form.setData('is_project_rebillable', event.target.checked)}
                    />
                    Allow project rebilling
                </label>
                <div className="flex gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save changes
                    </Button>
                </div>
            </form>
        </AppLayout>
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
