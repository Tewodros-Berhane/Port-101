import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type StockLevel = {
    id: string;
    product_name?: string | null;
    product_sku?: string | null;
    location_name?: string | null;
    location_type?: string | null;
    warehouse_name?: string | null;
    on_hand_quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    updated_at?: string | null;
};

type Props = {
    stockLevels: {
        data: StockLevel[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function InventoryStockLevelsIndex({ stockLevels }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                {
                    title: 'Stock Levels',
                    href: '/company/inventory/stock-levels',
                },
            ]}
        >
            <Head title="Stock Levels" />

            <div>
                <h1 className="text-xl font-semibold">Stock levels</h1>
                <p className="text-sm text-muted-foreground">
                    Ledger view of on-hand and reserved quantities.
                </p>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[980px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Product</th>
                            <th className="px-4 py-3 font-medium">SKU</th>
                            <th className="px-4 py-3 font-medium">Location</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Warehouse</th>
                            <th className="px-4 py-3 font-medium">On hand</th>
                            <th className="px-4 py-3 font-medium">Reserved</th>
                            <th className="px-4 py-3 font-medium">Available</th>
                            <th className="px-4 py-3 font-medium">Updated</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {stockLevels.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={9}
                                >
                                    No stock levels yet.
                                </td>
                            </tr>
                        )}
                        {stockLevels.data.map((row) => (
                            <tr key={row.id}>
                                <td className="px-4 py-3">{row.product_name ?? '-'}</td>
                                <td className="px-4 py-3">{row.product_sku ?? '-'}</td>
                                <td className="px-4 py-3">{row.location_name ?? '-'}</td>
                                <td className="px-4 py-3 capitalize">
                                    {row.location_type ?? '-'}
                                </td>
                                <td className="px-4 py-3">{row.warehouse_name ?? '-'}</td>
                                <td className="px-4 py-3">{row.on_hand_quantity.toFixed(4)}</td>
                                <td className="px-4 py-3">
                                    {row.reserved_quantity.toFixed(4)}
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {row.available_quantity.toFixed(4)}
                                </td>
                                <td className="px-4 py-3">
                                    {row.updated_at
                                        ? new Date(row.updated_at).toLocaleString()
                                        : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {stockLevels.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {stockLevels.links.map((link) => (
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
