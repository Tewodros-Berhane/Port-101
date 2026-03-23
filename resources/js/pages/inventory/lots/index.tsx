import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';

type Lot = {
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

type Props = {
    filters: {
        search?: string;
    };
    lots: {
        data: Lot[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function InventoryLotsIndex({ filters, lots }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Lots & Serials', href: '/company/inventory/lots' },
            ]}
        >
            <Head title="Lots & Serials" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Lots and serials</h1>
                    <p className="text-sm text-muted-foreground">
                        Inspect tracked stock by product and location.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/inventory">Back</Link>
                </Button>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="grid gap-2 md:max-w-sm">
                    <label className="text-sm font-medium" htmlFor="lot-search">
                        Search
                    </label>
                    <Input
                        id="lot-search"
                        defaultValue={filters.search ?? ''}
                        placeholder="Lot code, product, or location"
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                router.get(
                                    '/company/inventory/lots',
                                    { search: (event.target as HTMLInputElement).value },
                                    { preserveState: true, preserveScroll: true },
                                );
                            }
                        }}
                    />
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Code</th>
                            <th className="px-4 py-3 font-medium">Tracking</th>
                            <th className="px-4 py-3 font-medium">Product</th>
                            <th className="px-4 py-3 font-medium">Location</th>
                            <th className="px-4 py-3 font-medium">On hand</th>
                            <th className="px-4 py-3 font-medium">Reserved</th>
                            <th className="px-4 py-3 font-medium">Available</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {lots.data.length === 0 && (
                            <tr>
                                <td className="px-4 py-8 text-center text-muted-foreground" colSpan={8}>
                                    No lots or serials found.
                                </td>
                            </tr>
                        )}
                        {lots.data.map((lot) => (
                            <tr key={lot.id}>
                                <td className="px-4 py-3 font-medium">{lot.code}</td>
                                <td className="px-4 py-3 capitalize">{lot.tracking_mode}</td>
                                <td className="px-4 py-3">
                                    {lot.product_name ?? '-'}
                                    {lot.product_sku ? ` (${lot.product_sku})` : ''}
                                </td>
                                <td className="px-4 py-3">
                                    {lot.location_name ?? '-'}
                                    {lot.location_type ? ` (${lot.location_type})` : ''}
                                </td>
                                <td className="px-4 py-3">{lot.quantity_on_hand.toFixed(4)}</td>
                                <td className="px-4 py-3">{lot.quantity_reserved.toFixed(4)}</td>
                                <td className="px-4 py-3 font-medium">{lot.available_quantity.toFixed(4)}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={`/company/inventory/lots/${lot.id}`} className="text-sm font-medium text-primary">
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {lots.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {lots.links.map((link) => (
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
