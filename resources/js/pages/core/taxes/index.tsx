import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ModalFormShell } from '@/components/modals/modal-form-shell';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { masterDataBreadcrumbs } from '@/lib/page-navigation';

type Tax = {
    id: string;
    name: string;
    type: string;
    rate: string;
    is_active: boolean;
};

type Props = {
    taxes: {
        data: Tax[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function TaxesIndex({ taxes }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.taxes.view');
    const canManage = hasPermission('core.taxes.manage');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const createForm = useForm({
        name: '',
        type: 'percent',
        rate: '0',
        is_active: true,
    });

    const closeCreateModal = (open: boolean) => {
        setShowCreateModal(open);

        if (!open) {
            createForm.reset();
            createForm.clearErrors();
        }
    };

    return (
        <AppLayout
            breadcrumbs={masterDataBreadcrumbs({ title: 'Taxes', href: '/core/taxes' },)}
        >
            <Head title="Taxes" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Taxes</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage tax rules used in pricing and invoices.
                    </p>
                </div>
                {canManage && (
                    <Button type="button" onClick={() => setShowCreateModal(true)}>
                        New tax
                    </Button>
                )}
            </div>
            {canManage && (
                <ModalFormShell
                    open={showCreateModal}
                    onOpenChange={closeCreateModal}
                    title="New tax"
                    description="Add a tax rule for pricing and invoices."
                >
                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/core/taxes', {
                                onSuccess: () => closeCreateModal(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="tax-create-name">Name</Label>
                            <Input
                                id="tax-create-name"
                                value={createForm.data.name}
                                onChange={(event) =>
                                    createForm.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tax-create-type">Type</Label>
                            <select
                                id="tax-create-type"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={createForm.data.type}
                                onChange={(event) =>
                                    createForm.setData('type', event.target.value)
                                }
                            >
                                <option value="percent">Percent</option>
                                <option value="fixed">Fixed</option>
                            </select>
                            <InputError message={createForm.errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tax-create-rate">Rate</Label>
                            <Input
                                id="tax-create-rate"
                                type="number"
                                step="0.0001"
                                min="0"
                                value={createForm.data.rate}
                                onChange={(event) =>
                                    createForm.setData('rate', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.rate} />
                        </div>

                        <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                            <Checkbox
                                id="tax-create-active"
                                checked={createForm.data.is_active}
                                onCheckedChange={(value) =>
                                    createForm.setData('is_active', Boolean(value))
                                }
                            />
                            <Label htmlFor="tax-create-active">Active</Label>
                        </div>

                        <div className="flex items-center justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeCreateModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                Create tax
                            </Button>
                        </div>
                    </form>
                </ModalFormShell>
            )}
            {canView ? (
                <>
                    <div className="mt-6 overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-max text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Rate
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    {canManage && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {taxes.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 5 : 4}
                                        >
                                            No taxes yet.
                                        </td>
                                    </tr>
                                )}
                                {taxes.data.map((tax) => (
                                    <tr key={tax.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {tax.name}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {tax.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {tax.rate}
                                        </td>
                                        <td className="px-4 py-3">
                                            {tax.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/taxes/${tax.id}/edit`}
                                                    className="text-sm font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {taxes.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {taxes.links.map((link) => (
                                <Link
                                    key={link.label}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-1 text-sm ${
                                        link.active
                                            ? 'border-primary text-primary'
                                            : 'text-muted-foreground'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="mt-6 rounded-xl border p-6 text-sm text-muted-foreground">
                    You do not have access to view taxes.
                </div>
            )}
        </AppLayout>
    );
}
