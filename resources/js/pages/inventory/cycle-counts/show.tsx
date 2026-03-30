import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Line = {
    id: string;
    location_name?: string | null;
    product_name?: string | null;
    product_sku?: string | null;
    tracking_mode: string;
    lot_code?: string | null;
    expected_quantity: number;
    counted_quantity?: number | null;
    variance_quantity: number;
    estimated_unit_cost: number;
    variance_value: number;
    adjustment_move_id?: string | null;
    adjustment_move_reference?: string | null;
};

type AdjustmentMove = {
    id: string;
    reference: string;
    status: string;
    product_name?: string | null;
    quantity: number;
    completed_at?: string | null;
};

type Props = {
    cycleCount: {
        id: string;
        reference: string;
        status: string;
        approval_status: string;
        requires_approval: boolean;
        warehouse_name?: string | null;
        location_name?: string | null;
        line_count: number;
        total_expected_quantity: number;
        total_counted_quantity: number;
        total_variance_quantity: number;
        total_absolute_variance_quantity: number;
        total_variance_value: number;
        total_absolute_variance_value: number;
        notes?: string | null;
        started_at?: string | null;
        reviewed_at?: string | null;
        posted_at?: string | null;
        approved_at?: string | null;
        approved_by?: string | null;
        rejected_at?: string | null;
        rejected_by?: string | null;
        rejection_reason?: string | null;
        reviewed_by?: string | null;
        posted_by?: string | null;
        started_by?: string | null;
    };
    lines: Line[];
    adjustmentMoves: AdjustmentMove[];
    permissions: {
        can_start: boolean;
        can_update: boolean;
        can_review: boolean;
        can_post: boolean;
        can_cancel: boolean;
    };
};

export default function InventoryCycleCountShow({ cycleCount, lines, adjustmentMoves, permissions }: Props) {
    const form = useForm({
        lines: lines.map((line) => ({
            id: line.id,
            counted_quantity:
                line.counted_quantity === null || line.counted_quantity === undefined
                    ? ''
                    : String(line.counted_quantity),
        })),
    });

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.inventory, { title: 'Cycle Counts', href: '/company/inventory/cycle-counts' },
                { title: cycleCount.reference, href: `/company/inventory/cycle-counts/${cycleCount.id}` },)}
        >
            <Head title={cycleCount.reference} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">{cycleCount.reference}</h1>
                    <p className="text-sm text-muted-foreground">
                        {cycleCount.warehouse_name ?? 'No warehouse scope'} � {cycleCount.location_name ?? 'All scoped locations'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/inventory/cycle-counts" label="Back to cycle counts" variant="outline" />
                    {permissions.can_start && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                form.post(`/company/inventory/cycle-counts/${cycleCount.id}/start`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Start count
                        </Button>
                    )}
                    {permissions.can_review && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                form.post(`/company/inventory/cycle-counts/${cycleCount.id}/review`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Review count
                        </Button>
                    )}
                    {permissions.can_post && (
                        <Button
                            onClick={() =>
                                form.post(`/company/inventory/cycle-counts/${cycleCount.id}/post`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Post adjustments
                        </Button>
                    )}
                    {permissions.can_cancel && (
                        <Button
                            variant="destructive"
                            onClick={() =>
                                form.post(`/company/inventory/cycle-counts/${cycleCount.id}/cancel`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Cancel
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <Metric label="Status" value={cycleCount.status.replaceAll('_', ' ')} />
                <Metric label="Approval" value={cycleCount.approval_status.replaceAll('_', ' ')} />
                <Metric label="Expected" value={cycleCount.total_expected_quantity.toFixed(4)} />
                <Metric label="Counted" value={cycleCount.total_counted_quantity.toFixed(4)} />
                <Metric label="Net variance" value={cycleCount.total_variance_quantity.toFixed(4)} />
                <Metric label="Abs value" value={cycleCount.total_absolute_variance_value.toFixed(2)} />
            </div>

            {(cycleCount.approved_by || cycleCount.rejected_by || cycleCount.rejection_reason) && (
                <div className="mt-6 rounded-xl border p-4 text-sm">
                    <p className="font-medium">Approval context</p>
                    <div className="mt-2 grid gap-2 text-muted-foreground md:grid-cols-3">
                        <div>Approved by: {cycleCount.approved_by ?? '-'}</div>
                        <div>Rejected by: {cycleCount.rejected_by ?? '-'}</div>
                        <div>Reason: {cycleCount.rejection_reason ?? '-'}</div>
                    </div>
                </div>
            )}

            <form
                className="mt-6 rounded-xl border p-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        lines: data.lines.map((line) => ({
                            id: line.id,
                            counted_quantity:
                                line.counted_quantity === '' ? null : Number(line.counted_quantity),
                        })),
                    })).put(`/company/inventory/cycle-counts/${cycleCount.id}`, {
                        preserveScroll: true,
                    });
                }}
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">Count lines</h2>
                        <p className="text-xs text-muted-foreground">
                            Record counted quantities, then review the session to compute variances and approval state.
                        </p>
                    </div>
                    {permissions.can_update && (
                        <Button type="submit" disabled={form.processing}>
                            Save counts
                        </Button>
                    )}
                </div>

                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[1280px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Location</th>
                                <th className="px-3 py-2 font-medium">Product</th>
                                <th className="px-3 py-2 font-medium">Tracking</th>
                                <th className="px-3 py-2 font-medium">Lot / Serial</th>
                                <th className="px-3 py-2 font-medium">Expected</th>
                                <th className="px-3 py-2 font-medium">Counted</th>
                                <th className="px-3 py-2 font-medium">Variance</th>
                                <th className="px-3 py-2 font-medium">Unit cost</th>
                                <th className="px-3 py-2 font-medium">Variance value</th>
                                <th className="px-3 py-2 font-medium">Adjustment</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {lines.map((line, index) => (
                                <tr key={line.id}>
                                    <td className="px-3 py-2">{line.location_name ?? '-'}</td>
                                    <td className="px-3 py-2">
                                        {line.product_name ?? '-'}
                                        {line.product_sku ? ` (${line.product_sku})` : ''}
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {line.tracking_mode.replaceAll('_', ' ')}
                                    </td>
                                    <td className="px-3 py-2">{line.lot_code ?? '-'}</td>
                                    <td className="px-3 py-2">{line.expected_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">
                                        <input
                                            className="h-9 w-28 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                            disabled={!permissions.can_update}
                                            type="number"
                                            min="0"
                                            step={line.tracking_mode === 'serial' ? '1' : '0.0001'}
                                            value={form.data.lines[index]?.counted_quantity ?? ''}
                                            onChange={(event) => {
                                                const next = [...form.data.lines];
                                                next[index] = {
                                                    ...next[index],
                                                    counted_quantity: event.target.value,
                                                };
                                                form.setData('lines', next);
                                            }}
                                        />
                                    </td>
                                    <td className="px-3 py-2">{line.variance_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">{line.estimated_unit_cost.toFixed(2)}</td>
                                    <td className="px-3 py-2">{line.variance_value.toFixed(2)}</td>
                                    <td className="px-3 py-2">
                                        {line.adjustment_move_reference ? (
                                            <Link
                                                href={`/company/inventory/moves/${line.adjustment_move_id}/edit`}
                                                className="text-sm font-medium text-primary"
                                            >
                                                {line.adjustment_move_reference}
                                            </Link>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <InputError className="mt-3" message={form.errors.lines} />
            </form>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">Generated adjustments</h2>
                        <p className="text-xs text-muted-foreground">
                            Stock moves created when this cycle count is posted.
                        </p>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Reference</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Product</th>
                                <th className="px-3 py-2 font-medium">Qty</th>
                                <th className="px-3 py-2 font-medium">Completed</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {adjustmentMoves.length === 0 && (
                                <tr>
                                    <td className="px-3 py-6 text-center text-muted-foreground" colSpan={5}>
                                        No adjustment moves have been generated yet.
                                    </td>
                                </tr>
                            )}
                            {adjustmentMoves.map((move) => (
                                <tr key={move.id}>
                                    <td className="px-3 py-2">{move.reference}</td>
                                    <td className="px-3 py-2 capitalize">{move.status}</td>
                                    <td className="px-3 py-2">{move.product_name ?? '-'}</td>
                                    <td className="px-3 py-2">{move.quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">
                                        {move.completed_at ? new Date(move.completed_at).toLocaleString() : '-'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-sm font-semibold capitalize">{value}</p>
        </div>
    );
}
