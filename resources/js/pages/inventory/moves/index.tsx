import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Move = {
    id: string;
    reference?: string | null;
    move_type: string;
    status: string;
    product_name?: string | null;
    product_sku?: string | null;
    source_location_name?: string | null;
    destination_location_name?: string | null;
    sales_order_number?: string | null;
    quantity: number;
    reserved_at?: string | null;
    completed_at?: string | null;
};

type Props = {
    moves: {
        data: Move[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function InventoryMovesIndex({ moves }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Stock Moves', href: '/company/inventory/moves' },
            ]}
        >
            <Head title="Stock Moves" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Stock moves</h1>
                    <p className="text-sm text-muted-foreground">
                        Receipts, deliveries, and transfers.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/company/inventory/moves/create">New move</Link>
                </Button>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Ref</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Product</th>
                            <th className="px-4 py-3 font-medium">From</th>
                            <th className="px-4 py-3 font-medium">To</th>
                            <th className="px-4 py-3 font-medium">Order</th>
                            <th className="px-4 py-3 font-medium">Qty</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {moves.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={9}
                                >
                                    No stock moves yet.
                                </td>
                            </tr>
                        )}
                        {moves.data.map((move) => (
                            <tr key={move.id}>
                                <td className="px-4 py-3">{move.reference ?? '-'}</td>
                                <td className="px-4 py-3 capitalize">{move.move_type}</td>
                                <td className="px-4 py-3 capitalize">{move.status}</td>
                                <td className="px-4 py-3">
                                    {move.product_name ?? '-'}
                                    {move.product_sku ? ` (${move.product_sku})` : ''}
                                </td>
                                <td className="px-4 py-3">
                                    {move.source_location_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {move.destination_location_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {move.sales_order_number ?? '-'}
                                </td>
                                <td className="px-4 py-3">{move.quantity.toFixed(4)}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/inventory/moves/${move.id}/edit`}
                                        className="text-sm font-medium text-primary"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {moves.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {moves.links.map((link) => (
                        <Link
                            key={link.label}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1 text-sm ${
                                link.active
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
