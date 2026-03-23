import InventoryMoveLinesEditor, {
    type InventoryMoveLineInput,
} from '@/components/inventory/inventory-move-lines-editor';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

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

type Move = {
    id: string;
    reference?: string | null;
    move_type: string;
    status: string;
    source_location_id?: string | null;
    destination_location_id?: string | null;
    product_id: string;
    product_tracking_mode?: string | null;
    quantity: number;
    related_sales_order_id?: string | null;
    lines: InventoryMoveLineInput[];
    notes?: string | null;
    reserved_at?: string | null;
    completed_at?: string | null;
    cancelled_at?: string | null;
};

type Props = {
    move: Move;
    moveTypes: string[];
    products: ProductOption[];
    locations: LocationOption[];
    lots: LotOption[];
    salesOrders: SalesOrderOption[];
};

export default function InventoryMoveEdit({
    move,
    moveTypes,
    products,
    locations,
    lots,
    salesOrders,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('inventory.moves.manage');

    const form = useForm({
        reference: move.reference ?? '',
        move_type: move.move_type,
        source_location_id: move.source_location_id ?? '',
        destination_location_id: move.destination_location_id ?? '',
        product_id: move.product_id,
        quantity: move.quantity,
        lines: move.lines ?? [],
        notes: move.notes ?? '',
    });

    const actionForm = useForm({});
    const isDraft = move.status === 'draft';
    const isReserved = move.status === 'reserved';
    const isDone = move.status === 'done';
    const isCancelled = move.status === 'cancelled';
    const selectedProduct = products.find(
        (product) => product.id === form.data.product_id,
    );
    const trackingMode =
        selectedProduct?.tracking_mode ?? move.product_tracking_mode ?? 'none';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Stock Moves', href: '/company/inventory/moves' },
                {
                    title: move.reference ?? move.id,
                    href: `/company/inventory/moves/${move.id}/edit`,
                },
            ]}
        >
            <Head title={move.reference ?? 'Stock Move'} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit stock move</h1>
                    <p className="text-sm text-muted-foreground">
                        {move.reference ?? 'Unreferenced move'} - {move.status}
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory/moves">Back</Link>
                </Button>
            </div>

            <div className="mt-6 grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-3">
                <div>
                    <p className="text-xs text-muted-foreground">Reserved at</p>
                    <p>{move.reserved_at ? new Date(move.reserved_at).toLocaleString() : '-'}</p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Completed at</p>
                    <p>{move.completed_at ? new Date(move.completed_at).toLocaleString() : '-'}</p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Cancelled at</p>
                    <p>{move.cancelled_at ? new Date(move.cancelled_at).toLocaleString() : '-'}</p>
                </div>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/inventory/moves/${move.id}`);
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
                            disabled={!isDraft}
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
                                form.setData((data) => ({
                                    ...data,
                                    move_type: event.target.value,
                                    lines: [],
                                }))
                            }
                            disabled={!isDraft}
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
                            disabled={!isDraft}
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
                            disabled={!isDraft}
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
                            disabled={!isDraft}
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
                            disabled={!isDraft}
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
                            value={move.related_sales_order_id ?? ''}
                            disabled
                        >
                            <option value="">No linked sales order</option>
                            {salesOrders.map((order) => (
                                <option key={order.id} value={order.id}>
                                    {order.order_number}
                                </option>
                            ))}
                        </select>
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
                    disabled={!isDraft}
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
                        disabled={!isDraft}
                    />
                    <InputError message={form.errors.notes} />
                </div>

                {canManage && (
                    <div className="flex flex-wrap items-center gap-2">
                        {isDraft && (
                            <Button type="submit" disabled={form.processing}>
                                Save changes
                            </Button>
                        )}

                        {isDraft && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={actionForm.processing}
                                onClick={() =>
                                    actionForm.post(
                                        `/company/inventory/moves/${move.id}/reserve`,
                                    )
                                }
                            >
                                Reserve
                            </Button>
                        )}

                        {(isDraft || isReserved) && (
                            <Button
                                type="button"
                                disabled={actionForm.processing}
                                onClick={() =>
                                    actionForm.post(
                                        `/company/inventory/moves/${move.id}/complete`,
                                    )
                                }
                            >
                                Complete
                            </Button>
                        )}

                        {(isDraft || isReserved) && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={actionForm.processing}
                                onClick={() =>
                                    actionForm.post(
                                        `/company/inventory/moves/${move.id}/cancel`,
                                    )
                                }
                            >
                                Cancel
                            </Button>
                        )}

                        {isDraft && (
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={form.processing}
                                onClick={() =>
                                    form.delete(`/company/inventory/moves/${move.id}`)
                                }
                            >
                                Delete
                            </Button>
                        )}

                        {(isDone || isCancelled) && (
                            <p className="text-sm text-muted-foreground">
                                Move is {move.status} and can no longer be modified.
                            </p>
                        )}
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
