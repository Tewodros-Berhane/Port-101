import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type WarehouseOption = {
    id: string;
    name: string;
};

type Location = {
    id: string;
    warehouse_id?: string | null;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
};

type Props = {
    location: Location;
    warehouses: WarehouseOption[];
    locationTypes: string[];
};

export default function InventoryLocationEdit({
    location,
    warehouses,
    locationTypes,
}: Props) {
    const form = useForm({
        warehouse_id: location.warehouse_id ?? '',
        code: location.code,
        name: location.name,
        type: location.type,
        is_active: location.is_active,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Locations', href: '/company/inventory/locations' },
                {
                    title: location.code,
                    href: `/company/inventory/locations/${location.id}/edit`,
                },
            ]}
        >
            <Head title={location.code} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Edit location</h1>
                    <p className="text-sm text-muted-foreground">
                        Update location metadata and warehouse mapping.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory/locations">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/inventory/locations/${location.id}`);
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

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Save changes
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() =>
                            form.delete(`/company/inventory/locations/${location.id}`)
                        }
                        disabled={form.processing}
                    >
                        Delete
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
