import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type MoveRow = {
    id: string;
    reference?: string | null;
    move_type: string;
    status: string;
    product_name?: string | null;
    source_location_name?: string | null;
    destination_location_name?: string | null;
    quantity: number;
    created_at?: string | null;
};

type AlertRow = {
    id: string;
    product_name?: string | null;
    location_name?: string | null;
    location_type?: string | null;
    on_hand_quantity: number;
    reserved_quantity: number;
    available_quantity: number;
};

type Props = {
    kpis: {
        warehouses: number;
        locations: number;
        stock_levels: number;
        draft_moves: number;
        reserved_moves: number;
        done_moves_7d: number;
    };
    recentMoves: MoveRow[];
    stockAlerts: AlertRow[];
};

export default function InventoryIndex({ kpis, recentMoves, stockAlerts }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
            ]}
        >
            <Head title="Inventory" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Inventory module</h1>
                    <p className="text-sm text-muted-foreground">
                        Warehouse setup, stock levels, and move workflows.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link href="/company/inventory/moves/create">New move</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/inventory/warehouses/create">
                            New warehouse
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <Metric label="Warehouses" value={kpis.warehouses} />
                <Metric label="Locations" value={kpis.locations} />
                <Metric label="Stock rows" value={kpis.stock_levels} />
                <Metric label="Draft moves" value={kpis.draft_moves} />
                <Metric label="Reserved" value={kpis.reserved_moves} />
                <Metric label="Done (7d)" value={kpis.done_moves_7d} />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Recent moves</h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/inventory/moves">Open moves</Link>
                        </Button>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[860px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Ref</th>
                                    <th className="px-3 py-2 font-medium">Type</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Product</th>
                                    <th className="px-3 py-2 font-medium">From</th>
                                    <th className="px-3 py-2 font-medium">To</th>
                                    <th className="px-3 py-2 font-medium">Qty</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentMoves.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={7}
                                        >
                                            No moves yet.
                                        </td>
                                    </tr>
                                )}
                                {recentMoves.map((move) => (
                                    <tr key={move.id}>
                                        <td className="px-3 py-2">{move.reference ?? '-'}</td>
                                        <td className="px-3 py-2 capitalize">{move.move_type}</td>
                                        <td className="px-3 py-2 capitalize">{move.status}</td>
                                        <td className="px-3 py-2">{move.product_name ?? '-'}</td>
                                        <td className="px-3 py-2">
                                            {move.source_location_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {move.destination_location_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">{move.quantity.toFixed(4)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Stock alerts</h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/inventory/stock-levels">
                                Open stock levels
                            </Link>
                        </Button>
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[740px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Product</th>
                                    <th className="px-3 py-2 font-medium">Location</th>
                                    <th className="px-3 py-2 font-medium">Type</th>
                                    <th className="px-3 py-2 font-medium">On hand</th>
                                    <th className="px-3 py-2 font-medium">Reserved</th>
                                    <th className="px-3 py-2 font-medium">Available</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {stockAlerts.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={6}
                                        >
                                            No alert conditions.
                                        </td>
                                    </tr>
                                )}
                                {stockAlerts.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-3 py-2">{row.product_name ?? '-'}</td>
                                        <td className="px-3 py-2">{row.location_name ?? '-'}</td>
                                        <td className="px-3 py-2 capitalize">
                                            {row.location_type ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.on_hand_quantity.toFixed(4)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.reserved_quantity.toFixed(4)}
                                        </td>
                                        <td className="px-3 py-2 font-medium">
                                            {row.available_quantity.toFixed(4)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap gap-2">
                <Button variant="outline" asChild>
                    <Link href="/company/inventory/warehouses">Warehouses</Link>
                </Button>
                <Button variant="outline" asChild>
                    <Link href="/company/inventory/locations">Locations</Link>
                </Button>
                <Button variant="outline" asChild>
                    <Link href="/company/inventory/stock-levels">Stock levels</Link>
                </Button>
                <Button variant="outline" asChild>
                    <Link href="/company/inventory/moves">Stock moves</Link>
                </Button>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
