import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type HistoryRow = {
    id: string;
    move_id: string;
    reference?: string | null;
    move_type?: string | null;
    status?: string | null;
    source_location_name?: string | null;
    destination_location_name?: string | null;
    quantity: number;
    direction: string;
    created_at?: string | null;
};

type Props = {
    lot: {
        id: string;
        code: string;
        tracking_mode: string;
        product_name?: string | null;
        product_sku?: string | null;
        location_name?: string | null;
        location_type?: string | null;
        quantity_on_hand: number;
        quantity_reserved: number;
        available_quantity: number;
        received_at?: string | null;
        last_moved_at?: string | null;
    };
    history: HistoryRow[];
};

export default function InventoryLotShow({ lot, history }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Lots & Serials', href: '/company/inventory/lots' },
                { title: lot.code, href: `/company/inventory/lots/${lot.id}` },
            ]}
        >
            <Head title={lot.code} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">{lot.code}</h1>
                    <p className="text-sm text-muted-foreground">
                        {lot.product_name ?? '-'}
                        {lot.product_sku ? ` (${lot.product_sku})` : ''} · {lot.location_name ?? '-'}
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory/lots">Back</Link>
                </Button>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <Metric label="Tracking" value={lot.tracking_mode} />
                <Metric label="On hand" value={lot.quantity_on_hand.toFixed(4)} />
                <Metric label="Reserved" value={lot.quantity_reserved.toFixed(4)} />
                <Metric label="Available" value={lot.available_quantity.toFixed(4)} />
                <Metric label="Received" value={lot.received_at ? new Date(lot.received_at).toLocaleDateString() : '-'} />
                <Metric label="Last moved" value={lot.last_moved_at ? new Date(lot.last_moved_at).toLocaleString() : '-'} />
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">Movement history</h2>
                        <p className="text-xs text-muted-foreground">
                            Receipt, delivery, and transfer activity for this tracked unit.
                        </p>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[920px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Reference</th>
                                <th className="px-3 py-2 font-medium">Direction</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">From</th>
                                <th className="px-3 py-2 font-medium">To</th>
                                <th className="px-3 py-2 font-medium">Qty</th>
                                <th className="px-3 py-2 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {history.length === 0 && (
                                <tr>
                                    <td className="px-3 py-6 text-center text-muted-foreground" colSpan={8}>
                                        No movement history yet.
                                    </td>
                                </tr>
                            )}
                            {history.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-3 py-2">{row.reference ?? '-'}</td>
                                    <td className="px-3 py-2 capitalize">{row.direction}</td>
                                    <td className="px-3 py-2 capitalize">{row.move_type ?? '-'}</td>
                                    <td className="px-3 py-2 capitalize">{row.status ?? '-'}</td>
                                    <td className="px-3 py-2">{row.source_location_name ?? '-'}</td>
                                    <td className="px-3 py-2">{row.destination_location_name ?? '-'}</td>
                                    <td className="px-3 py-2">{row.quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">{row.created_at ? new Date(row.created_at).toLocaleString() : '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-sm font-semibold">{value}</p>
        </div>
    );
}
