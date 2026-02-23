import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Rfq = {
    id: string;
    rfq_number: string;
    status: string;
    partner_name?: string | null;
    rfq_date?: string | null;
    valid_until?: string | null;
    grand_total: number;
    order_number?: string | null;
    order_status?: string | null;
};

type Props = {
    rfqs: {
        data: Rfq[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function PurchaseRfqsIndex({ rfqs }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('purchasing.rfq.manage');

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Purchasing', href: '/company/purchasing' },
                { title: 'RFQs', href: '/company/purchasing/rfqs' },
            ]}
        >
            <Head title="Purchase RFQs" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Purchase RFQs</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage vendor requests and convert selected RFQs to purchase orders.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/company/purchasing/rfqs/create">New RFQ</Link>
                    </Button>
                )}
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[980px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">RFQ #</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Vendor</th>
                            <th className="px-4 py-3 font-medium">RFQ date</th>
                            <th className="px-4 py-3 font-medium">
                                Valid until
                            </th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">
                                Linked PO
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rfqs.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={8}
                                >
                                    No RFQs yet.
                                </td>
                            </tr>
                        )}
                        {rfqs.data.map((rfq) => (
                            <tr key={rfq.id}>
                                <td className="px-4 py-3 font-medium">
                                    {rfq.rfq_number}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {rfq.status.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3">
                                    {rfq.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {rfq.rfq_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {rfq.valid_until ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {rfq.grand_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {rfq.order_number
                                        ? `${rfq.order_number} (${rfq.order_status?.replace('_', ' ')})`
                                        : '-'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/purchasing/rfqs/${rfq.id}/edit`}
                                        className="text-sm font-medium text-primary"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {rfqs.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {rfqs.links.map((link) => (
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
