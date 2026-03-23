import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Option = {
    id: string;
    name: string;
    code?: string | null;
    warehouse_id?: string | null;
    sku?: string | null;
    tracking_mode?: string | null;
};

type Props = {
    warehouses: Option[];
    locations: Option[];
    products: Option[];
    form: {
        warehouse_id: string;
        location_id: string;
        product_ids: string[];
        notes: string;
    };
};

export default function InventoryCycleCountsCreate({ warehouses, locations, products, form: defaults }: Props) {
    const form = useForm({
        warehouse_id: defaults.warehouse_id,
        location_id: defaults.location_id,
        product_ids: defaults.product_ids,
        notes: defaults.notes,
    });

    const filteredLocations = form.data.warehouse_id
        ? locations.filter((location) => location.warehouse_id === form.data.warehouse_id)
        : locations;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Cycle Counts', href: '/company/inventory/cycle-counts' },
                { title: 'Create', href: '/company/inventory/cycle-counts/create' },
            ]}
        >
            <Head title="New Cycle Count" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New cycle count</h1>
                    <p className="text-sm text-muted-foreground">
                        Freeze current stock positions for a warehouse or location and record counted quantities.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory/cycle-counts">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/inventory/cycle-counts');
                }}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="warehouse_id">Warehouse</Label>
                        <select
                            id="warehouse_id"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.warehouse_id}
                            onChange={(event) => {
                                const warehouseId = event.target.value;
                                form.setData('warehouse_id', warehouseId);
                                if (
                                    warehouseId &&
                                    form.data.location_id &&
                                    !locations.some(
                                        (location) =>
                                            location.id === form.data.location_id &&
                                            location.warehouse_id === warehouseId,
                                    )
                                ) {
                                    form.setData('location_id', '');
                                }
                            }}
                        >
                            <option value="">Select warehouse</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>
                                    {warehouse.name} {warehouse.code ? `(${warehouse.code})` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.warehouse_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="location_id">Location</Label>
                        <select
                            id="location_id"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.location_id}
                            onChange={(event) => form.setData('location_id', event.target.value)}
                        >
                            <option value="">All matching locations</option>
                            {filteredLocations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name} {location.code ? `(${location.code})` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.location_id} />
                    </div>
                </div>

                <div className="mt-4 grid gap-2">
                    <Label htmlFor="product_ids">Product filter</Label>
                    <select
                        id="product_ids"
                        multiple
                        className="min-h-40 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        value={form.data.product_ids}
                        onChange={(event) =>
                            form.setData(
                                'product_ids',
                                Array.from(event.target.selectedOptions).map((option) => option.value),
                            )
                        }
                    >
                        {products.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name}
                                {product.sku ? ` (${product.sku})` : ''}
                                {product.tracking_mode && product.tracking_mode !== 'none'
                                    ? ` · ${product.tracking_mode}`
                                    : ''}
                            </option>
                        ))}
                    </select>
                    <p className="text-xs text-muted-foreground">
                        Optional. Leave empty to count all stock products within the selected warehouse or location.
                    </p>
                    <InputError message={form.errors.product_ids} />
                </div>

                <div className="mt-4 grid gap-2">
                    <Label htmlFor="notes">Notes</Label>
                    <textarea
                        id="notes"
                        className="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                    />
                    <InputError message={form.errors.notes} />
                </div>

                <div className="mt-6 flex flex-wrap justify-end gap-2">
                    <Button variant="outline" type="button" asChild>
                        <Link href="/company/inventory/cycle-counts">Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Create cycle count
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
