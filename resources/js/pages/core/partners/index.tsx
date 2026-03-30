import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DrawerFormShell } from '@/components/drawers/drawer-form-shell';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { masterDataBreadcrumbs } from '@/lib/page-navigation';

type Partner = {
    id: string;
    code?: string | null;
    name: string;
    type: string;
    email?: string | null;
    phone?: string | null;
    is_active: boolean;
};

type Props = {
    partners: {
        data: Partner[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function PartnersIndex({ partners }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.partners.view');
    const canManage = hasPermission('core.partners.manage');
    const [showCreateDrawer, setShowCreateDrawer] = useState(false);
    const createForm = useForm({
        name: '',
        code: '',
        type: 'customer',
        email: '',
        phone: '',
        is_active: true,
    });

    const closeCreateDrawer = (open: boolean) => {
        setShowCreateDrawer(open);

        if (!open) {
            createForm.reset();
            createForm.clearErrors();
        }
    };

    return (
        <AppLayout
            breadcrumbs={masterDataBreadcrumbs({ title: 'Partners', href: '/core/partners' },)}
        >
            <Head title="Partners" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Partners</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage customers and vendors.
                    </p>
                </div>
                {canManage && (
                    <Button
                        type="button"
                        onClick={() => setShowCreateDrawer(true)}
                    >
                        New partner
                    </Button>
                )}
            </div>

            {canManage && (
                <DrawerFormShell
                    open={showCreateDrawer}
                    onOpenChange={closeCreateDrawer}
                    title="New partner"
                    description="Add a customer or vendor without leaving the master-data list."
                    footer={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closeCreateDrawer(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                form="partner-create-drawer-form"
                                disabled={createForm.processing}
                            >
                                Create partner
                            </Button>
                        </>
                    }
                >
                    <form
                        id="partner-create-drawer-form"
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/core/partners', {
                                onSuccess: () => closeCreateDrawer(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="partner-create-name">Name</Label>
                            <Input
                                id="partner-create-name"
                                value={createForm.data.name}
                                onChange={(event) =>
                                    createForm.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="partner-create-code">Code</Label>
                            <Input
                                id="partner-create-code"
                                value={createForm.data.code}
                                onChange={(event) =>
                                    createForm.setData('code', event.target.value)
                                }
                            />
                            <InputError message={createForm.errors.code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="partner-create-type">Type</Label>
                            <select
                                id="partner-create-type"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={createForm.data.type}
                                onChange={(event) =>
                                    createForm.setData('type', event.target.value)
                                }
                            >
                                <option value="customer">Customer</option>
                                <option value="vendor">Vendor</option>
                                <option value="both">Both</option>
                            </select>
                            <InputError message={createForm.errors.type} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="partner-create-email">Email</Label>
                                <Input
                                    id="partner-create-email"
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="partner-create-phone">Phone</Label>
                                <Input
                                    id="partner-create-phone"
                                    value={createForm.data.phone}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.phone} />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                            <Checkbox
                                id="partner-create-active"
                                checked={createForm.data.is_active}
                                onCheckedChange={(value) =>
                                    createForm.setData('is_active', Boolean(value))
                                }
                            />
                            <Label htmlFor="partner-create-active">Active</Label>
                        </div>
                    </form>
                </DrawerFormShell>
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
                                        Code
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Phone
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
                                {partners.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 7 : 6}
                                        >
                                            No partners yet.
                                        </td>
                                    </tr>
                                )}
                                {partners.data.map((partner) => (
                                    <tr key={partner.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {partner.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {partner.code ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {partner.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {partner.email ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {partner.phone ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {partner.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/partners/${partner.id}/edit`}
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

                    {partners.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {partners.links.map((link) => (
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
                    You do not have access to view partners.
                </div>
            )}
        </AppLayout>
    );
}
