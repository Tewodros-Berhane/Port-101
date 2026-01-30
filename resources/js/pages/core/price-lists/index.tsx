import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type PriceList = {
    id: string;
    name: string;
    currency?: string | null;
    is_active: boolean;
};

type Props = {
    priceLists: {
        data: PriceList[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function PriceListsIndex({ priceLists }: Props) {
    return (
        <AppLayout
            breadcrumbs={[{ title: 'Price Lists', href: '/core/price-lists' }]}
        >
            <Head title="Price Lists" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Price lists</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage pricing by currency or segment.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/core/price-lists/create">New price list</Link>
                </Button>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Currency</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {priceLists.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={4}
                                >
                                    No price lists yet.
                                </td>
                            </tr>
                        )}
                        {priceLists.data.map((priceList) => (
                            <tr key={priceList.id}>
                                <td className="px-4 py-3 font-medium">
                                    {priceList.name}
                                </td>
                                <td className="px-4 py-3">
                                    {priceList.currency ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {priceList.is_active
                                        ? 'Active'
                                        : 'Inactive'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/core/price-lists/${priceList.id}/edit`}
                                        className="text-sm font-medium text-primary"
                                    >
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {priceLists.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {priceLists.links.map((link) => (
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
