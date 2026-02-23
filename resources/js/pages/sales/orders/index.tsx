import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Order = {
    id: string;
    order_number: string;
    status: string;
    partner_name?: string | null;
    quote_number?: string | null;
    order_date?: string | null;
    grand_total: number;
    requires_approval: boolean;
    approved_at?: string | null;
    confirmed_at?: string | null;
};

type Props = {
    orders: {
        data: Order[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function SalesOrdersIndex({ orders }: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('sales.orders.manage');

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Sales', href: '/company/sales' },
                { title: 'Orders', href: '/company/sales/orders' },
            ]}
        >
            <Head title="Sales Orders" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Orders</h1>
                    <p className="text-sm text-muted-foreground">
                        Confirm and track downstream order execution.
                    </p>
                </div>
                {canManage && (
                    <Button asChild>
                        <Link href="/company/sales/orders/create">New order</Link>
                    </Button>
                )}
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[980px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Order #</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Partner</th>
                            <th className="px-4 py-3 font-medium">Quote</th>
                            <th className="px-4 py-3 font-medium">Date</th>
                            <th className="px-4 py-3 font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">
                                Approval
                            </th>
                            <th className="px-4 py-3 font-medium">
                                Confirmed
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
                                    No orders yet.
                                </td>
                            </tr>
                        )}
                        {orders.data.map((order) => (
                            <tr key={order.id}>
                                <td className="px-4 py-3 font-medium">
                                    {order.order_number}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {order.status}
                                </td>
                                <td className="px-4 py-3">
                                    {order.partner_name ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.quote_number ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.order_date ?? '-'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.grand_total.toFixed(2)}
                                </td>
                                <td className="px-4 py-3">
                                    {order.requires_approval
                                        ? order.approved_at
                                            ? 'Approved'
                                            : 'Required'
                                        : 'Not required'}
                                </td>
                                <td className="px-4 py-3">
                                    {order.confirmed_at ? 'Yes' : 'No'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/sales/orders/${order.id}/edit`}
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
