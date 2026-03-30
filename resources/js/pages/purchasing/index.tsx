import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type RfqRow = {
    id: string;
    rfq_number: string;
    status: string;
    partner_name?: string | null;
    rfq_date?: string | null;
    grand_total: number;
    order_number?: string | null;
};

type OrderRow = {
    id: string;
    order_number: string;
    status: string;
    partner_name?: string | null;
    rfq_number?: string | null;
    order_date?: string | null;
    grand_total: number;
};

type Props = {
    kpis: {
        draft_rfqs: number;
        open_rfqs: number;
        selected_rfqs: number;
        draft_orders: number;
        ordered_orders: number;
        received_orders: number;
        open_commitments: number;
    };
    recentRfqs: RfqRow[];
    recentOrders: OrderRow[];
};

export default function PurchasingIndex({ kpis, recentRfqs, recentOrders }: Props) {
    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.purchasing, )}
        >
            <Head title="Purchasing" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Purchasing module</h1>
                    <p className="text-sm text-muted-foreground">
                        RFQ to PO to receipt workflow with vendor bill handoff.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link href="/company/purchasing/rfqs/create">New RFQ</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/purchasing/orders/create">New PO</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard label="Draft RFQs" value={String(kpis.draft_rfqs)} />
                <MetricCard label="Open RFQs" value={String(kpis.open_rfqs)} />
                <MetricCard label="Draft POs" value={String(kpis.draft_orders)} />
                <MetricCard
                    label="Open commitments"
                    value={kpis.open_commitments.toFixed(2)}
                />
                <MetricCard
                    label="Selected RFQs"
                    value={String(kpis.selected_rfqs)}
                />
                <MetricCard
                    label="Ordered POs"
                    value={String(kpis.ordered_orders)}
                />
                <MetricCard
                    label="Received/Billed POs"
                    value={String(kpis.received_orders)}
                />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-2">
                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Recent RFQs</h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/purchasing/rfqs">Open RFQs</Link>
                        </Button>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[740px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">RFQ</th>
                                    <th className="px-3 py-2 font-medium">Vendor</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Date</th>
                                    <th className="px-3 py-2 font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentRfqs.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No RFQs yet.
                                        </td>
                                    </tr>
                                )}
                                {recentRfqs.map((rfq) => (
                                    <tr key={rfq.id}>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/company/purchasing/rfqs/${rfq.id}/edit`}
                                                className="font-medium text-primary"
                                            >
                                                {rfq.rfq_number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            {rfq.partner_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 capitalize">
                                            {rfq.status.replace('_', ' ')}
                                        </td>
                                        <td className="px-3 py-2">
                                            {rfq.rfq_date ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {rfq.grand_total.toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold">Recent purchase orders</h2>
                        <Button variant="ghost" asChild>
                            <Link href="/company/purchasing/orders">Open POs</Link>
                        </Button>
                    </div>
                    <div className="mt-4 overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">PO</th>
                                    <th className="px-3 py-2 font-medium">Vendor</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">RFQ</th>
                                    <th className="px-3 py-2 font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentOrders.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-3 py-6 text-center text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No purchase orders yet.
                                        </td>
                                    </tr>
                                )}
                                {recentOrders.map((order) => (
                                    <tr key={order.id}>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/company/purchasing/orders/${order.id}/edit`}
                                                className="font-medium text-primary"
                                            >
                                                {order.order_number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            {order.partner_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 capitalize">
                                            {order.status.replace('_', ' ')}
                                        </td>
                                        <td className="px-3 py-2">
                                            {order.rfq_number ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {order.grand_total.toFixed(2)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
