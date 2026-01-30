import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Uom = {
    id: string;
    name: string;
    symbol?: string | null;
    is_active: boolean;
};

type Props = {
    uoms: {
        data: Uom[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function UomsIndex({ uoms }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Units', href: '/core/uoms' }]}>
            <Head title="Units" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">Units of measure</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage measurement units for products.
                    </p>
                </div>
                <Button asChild>
                    <Link href="/core/uoms/create">New unit</Link>
                </Button>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Name</th>
                            <th className="px-4 py-3 font-medium">Symbol</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {uoms.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={4}
                                >
                                    No units yet.
                                </td>
                            </tr>
                        )}
                        {uoms.data.map((uom) => (
                            <tr key={uom.id}>
                                <td className="px-4 py-3 font-medium">
                                    {uom.name}
                                </td>
                                <td className="px-4 py-3">
                                    {uom.symbol ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {uom.is_active ? 'Active' : 'Inactive'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/core/uoms/${uom.id}/edit`}
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

            {uoms.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {uoms.links.map((link) => (
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
