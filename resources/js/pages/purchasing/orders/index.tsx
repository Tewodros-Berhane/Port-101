import { Head, Link } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Order = {
    id: string;
    order_number: string;
    status: string;
    partner_name?: string | null;
    rfq_number?: string | null;
    order_date?: string | null;
    grand_total: number;
    requires_approval: boolean;
    received_at?: string | null;
    billed_at?: string | null;
};

type Props = {
    orders: {
        data: Order[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function PurchaseOrdersIndex({ orders }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('purchasing.po.manage');

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.purchasing, { title: 'Purchase Orders', href: '/company/purchasing/orders' },)}
        >
            <Head title="Purchase Orders" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Purchase Orders</h1>
                    <p className="text-sm text-muted-foreground">
                        Track approvals, ordering, receipts, and vendor bill handoffs.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/purchasing" label="Back to purchasing" variant="outline" />
                    {canManage && (
                        <Button asChild>
                            <Link href="/company/purchasing/orders/create">New PO</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">PO #</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Vendor</th>
                            <th className="px-4 py-3 font-medium">RFQ</th>
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">
                                Approval
                            </th>
                            <th className="px-4 py-3 font-medium">
                                Receipt/Bill
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {orders.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-4 py-8 text-center text-muted-foreground"
                                    colSpan={9}
                                >
                                    No purchase orders yet.
                                </td>
                            </tr>
                        )}
                        {orders.data.map((order) => (
                            <tr key={order.id}>
                                <td className="px-4 py-3 font-medium">
                                    {order.order_number}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {order.status.replace('_', ' ')}
                                </td>
                                <td className="px-4 py-3">
                                    {order.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.rfq_number ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.order_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.grand_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {order.requires_approval
                                        ? 'Required'
                                        : 'Not required'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.billed_at
                                        ? 'Billed'
                                        : order.received_at
                                          ? 'Received'
                                          : '-'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/purchasing/orders/${order.id}/edit`}
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

            {orders.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {orders.links.map((link) => (
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
