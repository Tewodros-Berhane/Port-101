import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';

type CycleCount = {
    id: string;
    reference: string;
    status: string;
    approval_status: string;
    warehouse_name?: string | null;
    location_name?: string | null;
    line_count: number;
    total_expected_quantity: number;
    total_counted_quantity: number;
    total_variance_quantity: number;
    total_absolute_variance_quantity: number;
    total_variance_value: number;
    total_absolute_variance_value: number;
    requires_approval: boolean;
    reviewed_at?: string | null;
    posted_at?: string | null;
    created_at?: string | null;
};

type Props = {
    filters: {
        status?: string;
        approval_status?: string;
    };
    metrics: {
        open: number;
        pending_approval: number;
        posted_30d: number;
        absolute_variance_value_open: number;
    };
    cycleCounts: {
        data: CycleCount[];
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export default function InventoryCycleCountsIndex({ filters, metrics, cycleCounts }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Cycle Counts', href: '/company/inventory/cycle-counts' },
            ]}
        >
            <Head title="Cycle Counts" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Cycle counts</h1>
                    <p className="text-sm text-muted-foreground">
                        Count sessions, variances, approvals, and posted stock corrections.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/inventory">Back</Link>
                    </Button>
                    <Button asChild>
                        <Link href="/company/inventory/cycle-counts/create">New cycle count</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Metric label="Open sessions" value={metrics.open} />
                <Metric label="Pending approval" value={metrics.pending_approval} />
                <Metric label="Posted (30d)" value={metrics.posted_30d} />
                <Metric label="Open variance value" value={metrics.absolute_variance_value_open.toFixed(2)} />
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2">
                        <label className="text-sm font-medium" htmlFor="status-filter">
                            Status
                        </label>
                        <select
                            id="status-filter"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                router.get(
                                    '/company/inventory/cycle-counts',
                                    {
                                        status: event.target.value || undefined,
                                        approval_status: filters.approval_status || undefined,
                                    },
                                    { preserveState: true, preserveScroll: true },
                                )
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="in_progress">In progress</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="posted">Posted</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <label className="text-sm font-medium" htmlFor="approval-filter">
                            Approval
                        </label>
                        <select
                            id="approval-filter"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filters.approval_status ?? ''}
                            onChange={(event) =>
                                router.get(
                                    '/company/inventory/cycle-counts',
                                    {
                                        status: filters.status || undefined,
                                        approval_status: event.target.value || undefined,
                                    },
                                    { preserveState: true, preserveScroll: true },
                                )
                            }
                        >
                            <option value="">All approval states</option>
                            <option value="not_required">Not required</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-xl border">
                <table className="w-full min-w-[1180px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Reference</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium">Approval</th>
                            <th className="px-4 py-3 font-medium">Warehouse</th>
                            <th className="px-4 py-3 font-medium">Location</th>
                            <th className="px-4 py-3 font-medium">Lines</th>
                            <th className="px-4 py-3 font-medium">Net variance</th>
                            <th className="px-4 py-3 font-medium">Abs variance</th>
                            <th className="px-4 py-3 font-medium">Abs value</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {cycleCounts.data.length === 0 && (
                            <tr>
                                <td className="px-4 py-8 text-center text-muted-foreground" colSpan={10}>
                                    No cycle counts found.
                                </td>
                            </tr>
                        )}
                        {cycleCounts.data.map((count) => (
                            <tr key={count.id}>
                                <td className="px-4 py-3 font-medium">{count.reference}</td>
                                <td className="px-4 py-3 capitalize">{count.status.replaceAll('_', ' ')}</td>
                                <td className="px-4 py-3 capitalize">{count.approval_status.replaceAll('_', ' ')}</td>
                                <td className="px-4 py-3">{count.warehouse_name ?? '-'}</td>
                                <td className="px-4 py-3">{count.location_name ?? '-'}</td>
                                <td className="px-4 py-3">{count.line_count}</td>
                                <td className="px-4 py-3">{count.total_variance_quantity.toFixed(4)}</td>
                                <td className="px-4 py-3">{count.total_absolute_variance_quantity.toFixed(4)}</td>
                                <td className="px-4 py-3">{count.total_absolute_variance_value.toFixed(2)}</td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/company/inventory/cycle-counts/${count.id}`}
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

            {cycleCounts.links.length > 1 && (
                <div className="mt-6 flex flex-wrap gap-2">
                    {cycleCounts.links.map((link) => (
                        <Link
                            key={link.label}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1 text-sm ${
                                link.active ? 'border-primary text-primary' : 'text-muted-foreground'
                            } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
