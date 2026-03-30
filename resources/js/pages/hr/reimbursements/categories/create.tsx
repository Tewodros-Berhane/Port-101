import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleLinks, moduleBreadcrumbs } from '@/lib/page-navigation';

type Props = {
    form: {
        name: string;
        code: string;
        default_expense_account_reference: string;
        requires_receipt: boolean;
        is_project_rebillable: boolean;
    };
};

export default function HrReimbursementCategoryCreate({ form: initialForm }: Props) {
    const form = useForm(initialForm);

    return (
        <AppLayout
            breadcrumbs={moduleBreadcrumbs(companyModuleLinks.hr, { title: 'Reimbursements', href: '/company/hr/reimbursements' },
                { title: 'New category', href: '/company/hr/reimbursements/categories/create' },)}
        >
            <Head title="New reimbursement category" />

            <div className="flex items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New reimbursement category</h1>
                    <p className="text-sm text-muted-foreground">
                        Create expense categories for travel, meals, subscriptions, and other reimbursable costs.
                    </p>
                </div>
                <BackLinkAction href="/company/hr/reimbursements" label="Back to reimbursements" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6 md:max-w-2xl"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/hr/reimbursements/categories');
                }}
            >
                <Field label="Name" error={form.errors.name}>
                    <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                </Field>
                <Field label="Code" error={form.errors.code}>
                    <Input
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value)}
                        placeholder="Optional short code"
                    />
                </Field>
                <Field label="Default expense account reference" error={form.errors.default_expense_account_reference}>
                    <Input
                        value={form.data.default_expense_account_reference}
                        onChange={(event) =>
                            form.setData('default_expense_account_reference', event.target.value)
                        }
                        placeholder="Optional accounting reference"
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
                        Create category
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
