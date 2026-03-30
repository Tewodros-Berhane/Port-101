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

type Uom = {
    id: string;
    name: string;
    symbol?: string | null;
    is_active: boolean;
};

type Props = {
    uoms: {
        data: Uom[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function UomsIndex({ uoms }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.uoms.view');
    const canManage = hasPermission('core.uoms.manage');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const createForm = useForm({
        name: '',
        symbol: '',
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
            breadcrumbs={masterDataBreadcrumbs({ title: 'Units', href: '/core/uoms' },)}
        >
            <Head title="Units" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Units of measure</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage measurement units for products.
                    </p>
                </div>
                {canManage && (
                    <Button type="button" onClick={() => setShowCreateModal(true)}>
                        New unit
                    </Button>
                )}
            </div>
            {canManage && (
                <ModalFormShell
                    open={showCreateModal}
                    onOpenChange={closeCreateModal}
                    title="New unit"
                    description="Add a unit of measure."
                >
                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/core/uoms', {
                                onSuccess: () => closeCreateModal(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="uom-create-name">Name</Label>
                            <Input
                                id="uom-create-name"
                                value={createForm.data.name}
                                onChange={(event) =>
                                    createForm.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="uom-create-symbol">Symbol</Label>
                            <Input
                                id="uom-create-symbol"
                                value={createForm.data.symbol}
                                onChange={(event) =>
                                    createForm.setData('symbol', event.target.value)
                                }
                            />
                            <InputError message={createForm.errors.symbol} />
                        </div>

                        <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                            <Checkbox
                                id="uom-create-active"
                                checked={createForm.data.is_active}
                                onCheckedChange={(value) =>
                                    createForm.setData('is_active', Boolean(value))
                                }
                            />
                            <Label htmlFor="uom-create-active">Active</Label>
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
                                Create unit
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
                                        Symbol
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
                                {uoms.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 4 : 3}
                                        >
                                            No units yet.
                                        </td>
                                    </tr>
                                )}
                                {uoms.data.map((uom) => (
                                    <tr key={uom.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {uom.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {uom.symbol ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {uom.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/uoms/${uom.id}/edit`}
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

                    {uoms.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {uoms.links.map((link) => (
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
                    You do not have access to view units.
                </div>
            )}
        </AppLayout>
    );
}
