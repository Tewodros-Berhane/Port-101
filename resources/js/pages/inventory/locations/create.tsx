import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type WarehouseOption = {
    id: string;
    name: string;
};

type Props = {
    warehouses: WarehouseOption[];
    locationTypes: string[];
};

export default function InventoryLocationCreate({ warehouses, locationTypes }: Props) {
    const form = useForm({
        warehouse_id: '',
        code: '',
        name: '',
        type: 'internal',
        is_active: true,
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.inventory, { title: 'Locations', href: '/company/inventory/locations' },
                { title: 'Create', href: '/company/inventory/locations/create' },)}
        >
            <Head title="New Location" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New location</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure where stock is stored or moved.
                    </p>
                </div>
                <BackLinkAction href="/company/inventory/locations" label="Back to locations" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/inventory/locations');
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor="code">Code</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value.toUpperCase())
                        }
                        maxLength={32}
                        required
                    />
                    <InputError message={form.errors.code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="type">Type</Label>
                        <select
                            id="type"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.type}
                            onChange={(event) =>
                                form.setData('type', event.target.value)
                            }
                        >
                            {locationTypes.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="warehouse_id">Warehouse</Label>
                        <select
                            id="warehouse_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.warehouse_id}
                            onChange={(event) =>
                                form.setData('warehouse_id', event.target.value)
                            }
                        >
                            <option value="">No warehouse</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>
                                    {warehouse.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.warehouse_id} />
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(value) =>
                            form.setData('is_active', Boolean(value))
                        }
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Create location
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
