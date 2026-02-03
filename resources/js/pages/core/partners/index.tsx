import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Partner = {
    id: string;
    code?: string | null;
    name: string;
    type: string;
    email?: string | null;
    phone?: string | null;
    is_active: boolean;
};

type Props = {
    partners: {
        data: Partner[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function PartnersIndex({ partners }: Props) {
    const { hasPermission } = usePermissions();
    const canView = hasPermission('core.partners.view');
    const canManage = hasPermission('core.partners.manage');

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Partners', href: '/core/partners' }]}
        >
            <Head title="Partners" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Partners</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage customers and vendors.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/core/partners/create">New partner</Link>
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
                                        Code
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Phone
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
                                {partners.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-8 text-center text-muted-foreground"
                                            colSpan={canManage ? 7 : 6}
                                        >
                                            No partners yet.
                                        </td>
                                    </tr>
                                )}
                                {partners.data.map((partner) => (
                                    <tr key={partner.id}>
                                        <td className="px-4 py-3 font-medium">
                                            {partner.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {partner.code ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {partner.type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {partner.email ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {partner.phone ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {partner.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={`/core/partners/${partner.id}/edit`}
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

                    {partners.links.length > 1 && (
                        <div className="mt-6 flex flex-wrap gap-2">
                            {partners.links.map((link) => (
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
                    You do not have access to view partners.
                </div>
            )}
        </AppLayout>
    );
}
