import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Warehouse = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
    locations_count: number;
};

type Props = {
    warehouses: {
        data: Warehouse[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function InventoryWarehousesIndex({ warehouses }: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.inventory, { title: 'Warehouses', href: '/company/inventory/warehouses' },)}
        >
            <Head title="Warehouses" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Warehouses</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure inventory storage sites.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/inventory" label="Back to inventory" variant="outline" />
                    <Button asChild>
                        <Link href="/company/inventory/warehouses/create">New warehouse</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[760px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Code</th>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Locations</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {warehouses.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={5}
                                >
                                    No warehouses yet.
                                </td>
                            </tr>
                        )}
                        {warehouses.data.map((warehouse) => (
                            <tr key={warehouse.id}>
                                <td className="px-4 py-3 font-medium">{warehouse.code}</td>
                                <td className="px-4 py-3">{warehouse.name}</td>
                                <td className="px-4 py-3">{warehouse.locations_count}</td>
                                <td className="px-4 py-3">
                                    {warehouse.is_active ? 'Active' : 'Inactive'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/inventory/warehouses/${warehouse.id}/edit`}
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

            {warehouses.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {warehouses.links.map((link) => (
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
