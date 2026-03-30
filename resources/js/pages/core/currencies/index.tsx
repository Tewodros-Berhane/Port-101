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

type Currency = {
    id: string;
    code: string;
    name: string;
    symbol?: string | null;
    decimal_places: number;
    is_active: boolean;
};

type Props = {
    currencies: {
        data: Currency[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function CurrenciesIndex({ currencies }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.currencies.view');
    const canManage = hasPermission('core.currencies.manage');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const createForm = useForm({
        code: '',
        name: '',
        symbol: '',
        decimal_places: 2,
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
            breadcrumbs={masterDataBreadcrumbs({ title: 'Currencies', href: '/core/currencies' },)}
        >
            <Head title="Currencies" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Currencies</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage currencies and formatting rules.
                    </p>
                </div>
                {canManage && (
                    <Button type="button" onClick={() => setShowCreateModal(true)}>
                        New currency
                    </Button>
                )}
            </div>
            {canManage && (
                <ModalFormShell
                    open={showCreateModal}
                    onOpenChange={closeCreateModal}
                    title="New currency"
                    description="Add currency and formatting details."
                >
                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/core/currencies', {
                                onSuccess: () => closeCreateModal(false),
                            });
                        }}
                    >
                        <div className="grid gap-2 md:grid-cols-2 md:gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="currency-create-code">Code</Label>
                                <Input
                                    id="currency-create-code"
                                    value={createForm.data.code}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={3}
                                    required
                                />
                                <InputError message={createForm.errors.code} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="currency-create-symbol">Symbol</Label>
                                <Input
                                    id="currency-create-symbol"
                                    value={createForm.data.symbol}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'symbol',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={createForm.errors.symbol} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="currency-create-name">Name</Label>
                            <Input
                                id="currency-create-name"
                                value={createForm.data.name}
                                onChange={(event) =>
                                    createForm.setData('name', event.target.value)
                                }
                                required
                            />
                            <InputError message={createForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="currency-create-decimals">
                                Decimal places
                            </Label>
                            <Input
                                id="currency-create-decimals"
                                type="number"
                                min={0}
                                max={6}
                                value={createForm.data.decimal_places}
                                onChange={(event) =>
                                    createForm.setData(
                                        'decimal_places',
                                        Number(event.target.value),
                                    )
                                }
                                required
                            />
                            <InputError
                                message={createForm.errors.decimal_places}
                            />
                        </div>

                        <div className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-3.5 py-3">
                            <Checkbox
                                id="currency-create-active"
                                checked={createForm.data.is_active}
                                onCheckedChange={(value) =>
                                    createForm.setData(
                                        'is_active',
                                        Boolean(value),
                                    )
                                }
                            />
                            <Label htmlFor="currency-create-active">Active</Label>
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
                                Create currency
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
                                        Code
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Symbol
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Decimals
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
                                {currencies.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 6 : 5}
                                        >
                                            No currencies yet.
                                        </td>
                                    </tr>
                                )}
                                {currencies.data.map((currency) => (
                                    <tr key={currency.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {currency.code}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.symbol ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.decimal_places}
                                        </td>
                                        <td className="px-4 py-3">
                                            {currency.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/currencies/${currency.id}/edit`}
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

                    {currencies.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {currencies.links.map((link) => (
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
                    You do not have access to view currencies.
                </div>
            )}
        </AppLayout>
    );
}
