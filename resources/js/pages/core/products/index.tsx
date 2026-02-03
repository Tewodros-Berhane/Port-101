import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Product = {
    id: string;
    sku?: string | null;
    name: string;
    type: string;
    uom?: string | null;
    tax?: string | null;
    is_active: boolean;
};

type Props = {
    products: {
        data: Product[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function ProductsIndex({ products }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.products.view');
    const canManage = hasPermission('core.products.manage');

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Products', href: '/core/products' }]}
        >
            <Head title="Products" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Products</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage items and services.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/core/products/create">New product</Link>
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
                                        SKU
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        UoM
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tax
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
                                {products.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 7 : 6}
                                        >
                                            No products yet.
                                        </td>
                                    </tr>
                                )}
                                {products.data.map((product) => (
                                    <tr key={product.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {product.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {product.sku ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {product.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {product.uom ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {product.tax ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {product.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/products/${product.id}/edit`}
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

                    {products.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {products.links.map((link) => (
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
                    You do not have access to view products.
                </div>
            )}
        </AppLayout>
    );
}
