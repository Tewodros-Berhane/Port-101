import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import InventoryMoveLinesEditor, {
    type InventoryMoveLineInput,
} from '@/components/inventory/inventory-move-lines-editor';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
    type: string;
    tracking_mode: string;
};

type LocationOption = {
    id: string;
    name: string;
    type: string;
};

type SalesOrderOption = {
    id: string;
    order_number: string;
};

type LotOption = {
    id: string;
    product_id: string;
    location_id: string;
    code: string;
    tracking_mode: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    available_quantity: number;
};

type Props = {
    move: {
        reference: string;
        move_type: string;
        source_location_id: string;
        destination_location_id: string;
        product_id: string;
        quantity: number;
        related_sales_order_id: string;
        lines: InventoryMoveLineInput[];
        notes: string;
    };
    moveTypes: string[];
    products: ProductOption[];
    locations: LocationOption[];
    lots: LotOption[];
    salesOrders: SalesOrderOption[];
};

export default function InventoryMoveCreate({
    move,
    moveTypes,
    products,
    locations,
    lots,
    salesOrders,
}: Props) {
    const form = useForm({
        reference: move.reference,
        move_type: move.move_type,
        source_location_id: move.source_location_id,
        destination_location_id: move.destination_location_id,
        product_id: move.product_id,
        quantity: move.quantity,
        related_sales_order_id: move.related_sales_order_id,
        lines: move.lines,
        notes: move.notes,
    });

    const selectedProduct = products.find(
        (product) => product.id === form.data.product_id,
    );
    const trackingMode = selectedProduct?.tracking_mode ?? 'none';

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.inventory, { title: 'Stock Moves', href: '/company/inventory/moves' },
                { title: 'Create', href: '/company/inventory/moves/create' },)}
        >
            <Head title="New Stock Move" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">New stock move</h1>
                    <p className="text-sm text-muted-foreground">
                        Create a receipt, delivery, transfer, or adjustment.
                    </p>
                </div>
                <BackLinkAction href="/company/inventory/moves" label="Back to stock moves" variant="ghost" />
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/inventory/moves');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="reference">Reference</Label>
                        <Input
                            id="reference"
                            value={form.data.reference}
                            onChange={(event) =>
                                form.setData('reference', event.target.value)
                            }
                        />
                        <InputError message={form.errors.reference} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="move_type">Move type</Label>
                        <select
                            id="move_type"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.move_type}
                            onChange={(event) =>
                                form.setData('move_type', event.target.value)
                            }
                        >
                            {moveTypes.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.move_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="product_id">Product</Label>
                        <select
                            id="product_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.product_id}
                            onChange={(event) =>
                                form.setData((data) => ({
                                    ...data,
                                    product_id: event.target.value,
                                    lines: [],
                                }))
                            }
                            required
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
                        <Label htmlFor="quantity">Quantity</Label>
                        <Input
                            id="quantity"
                            type="number"
                            min={0.0001}
                            step={trackingMode === 'serial' ? 1 : 0.0001}
                            value={String(form.data.quantity)}
                            onChange={(event) =>
                                form.setData(
                                    'quantity',
                                    Number(event.target.value || 0),
                                )
                            }
                        />
                        <InputError message={form.errors.quantity} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="source_location_id">Source location</Label>
                        <select
                            id="source_location_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.source_location_id}
                            onChange={(event) =>
                                form.setData((data) => ({
                                    ...data,
                                    source_location_id: event.target.value,
                                    lines:
                                        data.move_type === 'delivery'
                                        || data.move_type === 'transfer'
                                            ? []
                                            : data.lines,
                                }))
                            }
                        >
                            <option value="">No source location</option>
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name} ({location.type})
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.source_location_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="destination_location_id">
                            Destination location
                        </Label>
                        <select
                            id="destination_location_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.destination_location_id}
                            onChange={(event) =>
                                form.setData(
                                    'destination_location_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">No destination location</option>
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name} ({location.type})
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={form.errors.destination_location_id}
                        />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="related_sales_order_id">
                            Linked sales order
                        </Label>
                        <select
                            id="related_sales_order_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.related_sales_order_id}
                            onChange={(event) =>
                                form.setData(
                                    'related_sales_order_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">No linked sales order</option>
                            {salesOrders.map((order) => (
                                <option key={order.id} value={order.id}>
                                    {order.order_number}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={form.errors.related_sales_order_id}
                        />
                    </div>
                </div>

                <InventoryMoveLinesEditor
                    trackingMode={trackingMode}
                    moveType={form.data.move_type}
                    quantity={form.data.quantity}
                    productId={form.data.product_id}
                    sourceLocationId={form.data.source_location_id}
                    lines={form.data.lines}
                    lotOptions={lots}
                    errors={form.errors}
                    onChange={(lines) => form.setData('lines', lines)}
                />

                <div className="grid gap-2">
                    <Label htmlFor="notes">Notes</Label>
                    <textarea
                        id="notes"
                        className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                    <InputError message={form.errors.notes} />
                </div>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        Create move
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
