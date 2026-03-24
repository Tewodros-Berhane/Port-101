import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type Option = {
    id: string;
    name: string;
    code?: string | null;
    sku?: string | null;
};

type Props = {
    rule: {
        product_id: string;
        location_id: string;
        preferred_vendor_id: string;
        min_quantity: number;
        max_quantity: number;
        reorder_quantity: string;
        lead_time_days: string;
        is_active: boolean;
        notes: string;
    };
    products: Option[];
    locations: Option[];
    vendors: Option[];
};

export default function InventoryReorderingCreate({ rule, products, locations, vendors }: Props) {
    const form = useForm({
        ...rule,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Reordering', href: '/company/inventory/reordering' },
                { title: 'Create', href: '/company/inventory/reordering/create' },
            ]}
        >
            <Head title="New Reordering Rule" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New reordering rule</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure the stock threshold and replenishment target for a product/location pair.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory/reordering">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/inventory/reordering');
                }}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="product_id">Product</Label>
                        <select
                            id="product_id"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.product_id}
                            onChange={(event) => form.setData('product_id', event.target.value)}
                        >
                            <option value="">Select product</option>
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.name}
                                    {product.sku ? ` (${product.sku})` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.product_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="location_id">Location</Label>
                        <select
                            id="location_id"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.location_id}
                            onChange={(event) => form.setData('location_id', event.target.value)}
                        >
                            <option value="">Select location</option>
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name}
                                    {location.code ? ` (${location.code})` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.location_id} />
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="min_quantity">Minimum quantity</Label>
                        <input
                            id="min_quantity"
                            type="number"
                            min="0"
                            step="0.0001"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.min_quantity}
                            onChange={(event) => form.setData('min_quantity', Number(event.target.value))}
                        />
                        <InputError message={form.errors.min_quantity} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="max_quantity">Maximum quantity</Label>
                        <input
                            id="max_quantity"
                            type="number"
                            min="0"
                            step="0.0001"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.max_quantity}
                            onChange={(event) => form.setData('max_quantity', Number(event.target.value))}
                        />
                        <InputError message={form.errors.max_quantity} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="reorder_quantity">Minimum reorder quantity</Label>
                        <input
                            id="reorder_quantity"
                            type="number"
                            min="0"
                            step="0.0001"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.reorder_quantity}
                            onChange={(event) => form.setData('reorder_quantity', event.target.value)}
                        />
                        <InputError message={form.errors.reorder_quantity} />
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="preferred_vendor_id">Preferred vendor</Label>
                        <select
                            id="preferred_vendor_id"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.preferred_vendor_id}
                            onChange={(event) => form.setData('preferred_vendor_id', event.target.value)}
                        >
                            <option value="">No preferred vendor</option>
                            {vendors.map((vendor) => (
                                <option key={vendor.id} value={vendor.id}>
                                    {vendor.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.preferred_vendor_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lead_time_days">Lead time days</Label>
                        <input
                            id="lead_time_days"
                            type="number"
                            min="0"
                            step="1"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={form.data.lead_time_days}
                            onChange={(event) => form.setData('lead_time_days', event.target.value)}
                        />
                        <InputError message={form.errors.lead_time_days} />
                    </div>
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

                <label className="mt-4 flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) => form.setData('is_active', event.target.checked)}
                    />
                    Rule is active
                </label>

                <div className="mt-6 flex flex-wrap justify-end gap-2">
                    <Button variant="outline" type="button" asChild>
                        <Link href="/company/inventory/reordering">Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Create rule
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
