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

type Address = {
    id: string;
    partner?: string | null;
    type: string;
    line1: string;
    city?: string | null;
    state?: string | null;
    country_code?: string | null;
    is_primary: boolean;
};

type Props = {
    addresses: {
        data: Address[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    partners: PartnerOption[];
};

type PartnerOption = {
    id: string;
    name: string;
    code?: string | null;
};

export default function AddressesIndex({ addresses, partners }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.addresses.view');
    const canManage = hasPermission('core.addresses.manage');
    const [showCreateDrawer, setShowCreateDrawer] = useState(false);
    const createForm = useForm({
        partner_id: '',
        type: 'billing',
        line1: '',
        line2: '',
        city: '',
        state: '',
        postal_code: '',
        country_code: '',
        is_primary: false,
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
            breadcrumbs={masterDataBreadcrumbs({ title: 'Addresses', href: '/core/addresses' },)}
        >
            <Head title="Addresses" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Addresses</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage partner addresses.
                    </p>
                </div>
                {canManage && (
                    <Button
                        type="button"
                        onClick={() => setShowCreateDrawer(true)}
                    >
                        New address
                    </Button>
                )}
            </div>
            {canManage && (
                <DrawerFormShell
                    open={showCreateDrawer}
                    onOpenChange={closeCreateDrawer}
                    title="New address"
                    description="Add a partner address without leaving the current list."
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
                                form="address-create-drawer-form"
                                disabled={createForm.processing}
                            >
                                Create address
                            </Button>
                        </>
                    }
                >
                    <form
                        id="address-create-drawer-form"
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/core/addresses', {
                                onSuccess: () => closeCreateDrawer(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="address-create-partner">Partner</Label>
                            <select
                                id="address-create-partner"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={createForm.data.partner_id}
                                onChange={(event) =>
                                    createForm.setData(
                                        'partner_id',
                                        event.target.value,
                                    )
                                }
                                required
                            >
                                <option value="">Select partner</option>
                                {partners.map((partner) => (
                                    <option key={partner.id} value={partner.id}>
                                        {partner.name}
                                        {partner.code ? ` (${partner.code})` : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={createForm.errors.partner_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address-create-type">Type</Label>
                            <select
                                id="address-create-type"
                                className="h-10 rounded-[var(--radius-control)] border border-input bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] outline-none transition-[border-color,box-shadow,background-color] duration-150 focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                value={createForm.data.type}
                                onChange={(event) =>
                                    createForm.setData('type', event.target.value)
                                }
                            >
                                <option value="billing">Billing</option>
                                <option value="shipping">Shipping</option>
                                <option value="other">Other</option>
                            </select>
                            <InputError message={createForm.errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address-create-line1">
                                Address line 1
                            </Label>
                            <Input
                                id="address-create-line1"
                                value={createForm.data.line1}
                                onChange={(event) =>
                                    createForm.setData('line1', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.line1} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address-create-line2">
                                Address line 2
                            </Label>
                            <Input
                                id="address-create-line2"
                                value={createForm.data.line2}
                                onChange={(event) =>
                                    createForm.setData('line2', event.target.value)
                                }
                            />
                            <InputError message={createForm.errors.line2} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="address-create-city">City</Label>
                                <Input
                                    id="address-create-city"
                                    value={createForm.data.city}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'city',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.city} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="address-create-state">State</Label>
                                <Input
                                    id="address-create-state"
                                    value={createForm.data.state}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'state',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.state} />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="address-create-postal">
                                    Postal code
                                </Label>
                                <Input
                                    id="address-create-postal"
                                    value={createForm.data.postal_code}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'postal_code',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={createForm.errors.postal_code}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="address-create-country">
                                    Country code
                                </Label>
                                <Input
                                    id="address-create-country"
                                    value={createForm.data.country_code}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'country_code',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={2}
                                />
                                <InputError
                                    message={createForm.errors.country_code}
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                            <Checkbox
                                id="address-create-primary"
                                checked={createForm.data.is_primary}
                                onCheckedChange={(value) =>
                                    createForm.setData('is_primary', Boolean(value))
                                }
                            />
                            <Label htmlFor="address-create-primary">
                                Primary address
                            </Label>
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
                                        Partner
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Line 1
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        City
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        State
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Country
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Primary
                                    </th>
                                    {canManage && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {addresses.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 8 : 7}
                                        >
                                            No addresses yet.
                                        </td>
                                    </tr>
                                )}
                                {addresses.data.map((address) => (
                                    <tr key={address.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {address.partner ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {address.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {address.line1}
                                        </td>
                                        <td className="px-4 py-3">
                                            {address.city ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {address.state ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {address.country_code ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {address.is_primary
                                                ? 'Primary'
                                                : '-'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/addresses/${address.id}/edit`}
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

                    {addresses.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {addresses.links.map((link) => (
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
                    You do not have access to view addresses.
                </div>
            )}
        </AppLayout>
    );
}
