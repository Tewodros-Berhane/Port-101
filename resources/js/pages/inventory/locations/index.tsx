import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Location = {
    id: string;
    warehouse_name?: string | null;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
};

type Props = {
    locations: {
        data: Location[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function InventoryLocationsIndex({ locations }: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.inventory, { title: 'Locations', href: '/company/inventory/locations' },)}
        >
            <Head title="Locations" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Locations</h1>
                    <p className="text-sm text-muted-foreground">
                        Define internal and virtual stock locations.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/inventory" label="Back to inventory" variant="outline" />
                    <Button asChild>
                        <Link href="/company/inventory/locations/create">New location</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Code</th>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Warehouse</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {locations.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={6}
                                >
                                    No locations yet.
                                </td>
                            </tr>
                        )}
                        {locations.data.map((location) => (
                            <tr key={location.id}>
                                <td className="px-4 py-3 font-medium">{location.code}</td>
                                <td className="px-4 py-3">{location.name}</td>
                                <td className="px-4 py-3 capitalize">{location.type}</td>
                                <td className="px-4 py-3">{location.warehouse_name ?? '-'}</td>
                                <td className="px-4 py-3">
                                    {location.is_active ? 'Active' : 'Inactive'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/inventory/locations/${location.id}/edit`}
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

            {locations.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {locations.links.map((link) => (
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
