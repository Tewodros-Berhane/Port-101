import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    leadCounts: Record<string, number>;
    quoteCounts: Record<string, number>;
    orderCounts: Record<string, number>;
    pipelineValue: number;
};

export default function SalesIndex({
    leadCounts,
    quoteCounts,
    orderCounts,
    pipelineValue,
}: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Sales', href: '/company/sales' },
            ]}
        >
            <Head title="Sales" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Sales module</h1>
                    <p className="text-sm text-muted-foreground">
                        Lead-to-quote-to-order workflow with approval hooks.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link href="/company/sales/leads/create">New lead</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/sales/quotes/create">
                            New quote
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/company/sales/orders/create">
                            New order
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Pipeline value"
                    value={pipelineValue.toFixed(2)}
                />
                <MetricCard
                    label="Open leads"
                    value={String(
                        (leadCounts.new ?? 0) +
                            (leadCounts.qualified ?? 0) +
                            (leadCounts.quoted ?? 0),
                    )}
                />
                <MetricCard
                    label="Open quotes"
                    value={String(
                        (quoteCounts.draft ?? 0) +
                            (quoteCounts.sent ?? 0) +
                            (quoteCounts.approved ?? 0),
                    )}
                />
                <MetricCard
                    label="Draft orders"
                    value={String(orderCounts.draft ?? 0)}
                />
            </div>

            <div className="mt-6 grid gap-4 xl:grid-cols-3">
                <StatusCard
                    title="Lead stages"
                    rows={[
                        ['New', leadCounts.new ?? 0],
                        ['Qualified', leadCounts.qualified ?? 0],
                        ['Quoted', leadCounts.quoted ?? 0],
                        ['Won', leadCounts.won ?? 0],
                        ['Lost', leadCounts.lost ?? 0],
                    ]}
                    href="/company/sales/leads"
                />
                <StatusCard
                    title="Quote status"
                    rows={[
                        ['Draft', quoteCounts.draft ?? 0],
                        ['Sent', quoteCounts.sent ?? 0],
                        ['Approved', quoteCounts.approved ?? 0],
                        ['Confirmed', quoteCounts.confirmed ?? 0],
                    ]}
                    href="/company/sales/quotes"
                />
                <StatusCard
                    title="Order status"
                    rows={[
                        ['Draft', orderCounts.draft ?? 0],
                        ['Confirmed', orderCounts.confirmed ?? 0],
                        ['Fulfilled', orderCounts.fulfilled ?? 0],
                        ['Invoiced', orderCounts.invoiced ?? 0],
                    ]}
                    href="/company/sales/orders"
                />
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

function StatusCard({
    title,
    rows,
    href,
}: {
    title: string;
    rows: Array<[string, number]>;
    href: string;
}) {
    return (
        <div className="rounded-xl border p-4">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-sm font-semibold">{title}</h2>
                <Button variant="ghost" asChild>
                    <Link href={href}>Open</Link>
                </Button>
            </div>
            <div className="mt-4 space-y-2 text-sm">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex items-center justify-between">
                        <span className="text-muted-foreground">{label}</span>
                        <span className="font-medium">{value}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
