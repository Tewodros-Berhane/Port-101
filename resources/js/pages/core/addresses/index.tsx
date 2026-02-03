import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Address = {
    id: string;
    partner?: string | null;
    type: string;
    line1: string;
    city?: string | null;
    state?: string | null;
    country_code?: string | null;
    is_primary: boolean;
};

type Props = {
    addresses: {
        data: Address[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function AddressesIndex({ addresses }: Props) {
    return (
        <AppLayout
            breadcrumbs={[{ title: 'Addresses', href: '/core/addresses' }]}
        >
            <Head title="Addresses" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Addresses</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage partner addresses.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/core/addresses/create">New address</Link>
                </Button>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Partner</th>
                            <th className="px-4 py-3 font-medium">Type</th>
                            <th className="px-4 py-3 font-medium">Line 1</th>
                            <th className="px-4 py-3 font-medium">City</th>
                            <th className="px-4 py-3 font-medium">State</th>
                            <th className="px-4 py-3 font-medium">Country</th>
                            <th className="px-4 py-3 font-medium">Primary</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {addresses.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={8}
                                >
                                    No addresses yet.
                                </td>
                            </tr>
                        )}
                        {addresses.data.map((address) => (
                            <tr key={address.id}>
                                <td className="px-4 py-3 font-medium">
                                    {address.partner ?? '—'}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {address.type}
                                </td>
                                <td className="px-4 py-3">{address.line1}</td>
                                <td className="px-4 py-3">
                                    {address.city ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {address.state ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {address.country_code ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {address.is_primary ? 'Primary' : '—'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/core/addresses/${address.id}/edit`}
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

            {addresses.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {addresses.links.map((link) => (
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
