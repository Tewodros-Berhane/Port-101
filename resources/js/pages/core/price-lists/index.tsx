import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
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
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.price_lists.view');
    const canManage = hasPermission('core.price_lists.manage');

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Price Lists', href: '/core/price-lists' },
            ]}
        >
            <Head title="Price Lists" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Price lists</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage pricing by currency or segment.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/core/price-lists/create">
                            New price list
                        </Link>
                    </Button>
                )}
            </div>
            {canView ? (
                <>
                    <div className="mt-6 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Currency
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    {canManage && (
                                        <th className="px-4 py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {priceLists.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 4 : 3}
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
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/price-lists/${priceList.id}/edit`}
                                                    className="text-sm font-medium text-primary"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        )}
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
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="mt-6 rounded-xl border p-6 text-sm text-muted-foreground">
                    You do not have access to view price lists.
                </div>
            )}
        </AppLayout>
    );
}
