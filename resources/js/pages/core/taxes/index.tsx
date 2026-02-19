import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Tax = {
    id: string;
    name: string;
    type: string;
    rate: string;
    is_active: boolean;
};

type Props = {
    taxes: {
        data: Tax[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function TaxesIndex({ taxes }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.taxes.view');
    const canManage = hasPermission('core.taxes.manage');

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Master Data', href: '/core/partners' },
                { title: 'Taxes', href: '/core/taxes' },
            ]}
        >
            <Head title="Taxes" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Taxes</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage tax rules used in pricing and invoices.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/core/taxes/create">New tax</Link>
                    </Button>
                )}
            </div>
            {canView ? (
                <>
                    <div className="mt-6 overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-max text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Rate
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
                                {taxes.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 5 : 4}
                                        >
                                            No taxes yet.
                                        </td>
                                    </tr>
                                )}
                                {taxes.data.map((tax) => (
                                    <tr key={tax.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {tax.name}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {tax.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {tax.rate}
                                        </td>
                                        <td className="px-4 py-3">
                                            {tax.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/taxes/${tax.id}/edit`}
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

                    {taxes.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {taxes.links.map((link) => (
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
                    You do not have access to view taxes.
                </div>
            )}
        </AppLayout>
    );
}
