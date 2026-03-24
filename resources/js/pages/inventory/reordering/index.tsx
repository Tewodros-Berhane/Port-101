import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Option = {
    id: string;
    name: string;
    code?: string | null;
    sku?: string | null;
    type?: string | null;
};

type Rule = {
    id: string;
    product_name?: string | null;
    product_sku?: string | null;
    location_name?: string | null;
    location_code?: string | null;
    preferred_vendor_name?: string | null;
    min_quantity: number;
    max_quantity: number;
    reorder_quantity?: number | null;
    lead_time_days?: number | null;
    is_active: boolean;
    last_evaluated_at?: string | null;
    notes?: string | null;
    metrics: {
        available_quantity: number;
        inbound_quantity: number;
        projected_quantity: number;
        suggested_quantity: number;
        requires_replenishment: boolean;
    };
};

type Suggestion = {
    id: string;
    status: string;
    product_name?: string | null;
    product_sku?: string | null;
    location_name?: string | null;
    location_code?: string | null;
    preferred_vendor_id?: string | null;
    preferred_vendor_name?: string | null;
    rfq_id?: string | null;
    rfq_number?: string | null;
    rfq_status?: string | null;
    available_quantity: number;
    inbound_quantity: number;
    projected_quantity: number;
    min_quantity: number;
    max_quantity: number;
    suggested_quantity: number;
    triggered_at?: string | null;
    converted_at?: string | null;
    dismissed_at?: string | null;
    resolved_at?: string | null;
    notes?: string | null;
    can_dismiss: boolean;
    can_convert: boolean;
};

type Props = {
    filters: {
        status: string;
    };
    metrics: {
        active_rules: number;
        open_suggestions: number;
        converted_30d: number;
        projected_shortages: number;
    };
    rules: Rule[];
    suggestions: {
        data: Suggestion[];
    };
    products: Option[];
    locations: Option[];
    vendors: Option[];
    permissions: {
        can_manage: boolean;
        can_scan: boolean;
    };
};

export default function InventoryReorderingIndex({
    filters,
    metrics,
    rules,
    suggestions,
    vendors,
    permissions,
}: Props) {
    const [vendorSelections, setVendorSelections] = useState<Record<string, string>>({});
    const suggestionRows = suggestions.data ?? [];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Inventory', href: '/company/inventory' },
                { title: 'Reordering', href: '/company/inventory/reordering' },
            ]}
        >
            <Head title="Inventory Reordering" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Inventory reordering</h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor low-stock positions, maintain reorder rules, and convert replenishment needs into RFQs.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/company/inventory">Back</Link>
                    </Button>
                    {permissions.can_scan && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post('/company/inventory/reordering/scan', {}, { preserveScroll: true })
                            }
                        >
                            Run scan
                        </Button>
                    )}
                    {permissions.can_manage && (
                        <Button asChild>
                            <Link href="/company/inventory/reordering/create">New rule</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Metric label="Active rules" value={metrics.active_rules} />
                <Metric label="Open suggestions" value={metrics.open_suggestions} />
                <Metric label="Converted 30d" value={metrics.converted_30d} />
                <Metric label="Projected shortages" value={metrics.projected_shortages} />
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">Replenishment suggestions</h2>
                        <p className="text-xs text-muted-foreground">
                            Open suggestions are generated by scans from projected stock falling below the rule minimum.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Label htmlFor="status-filter" className="text-xs text-muted-foreground">
                            Status
                        </Label>
                        <select
                            id="status-filter"
                            className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                            value={filters.status}
                            onChange={(event) =>
                                router.get(
                                    '/company/inventory/reordering',
                                    { status: event.target.value },
                                    { preserveState: true, preserveScroll: true, replace: true },
                                )
                            }
                        >
                            <option value="open">Open</option>
                            <option value="converted">Converted</option>
                            <option value="dismissed">Dismissed</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[1480px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Product</th>
                                <th className="px-3 py-2 font-medium">Location</th>
                                <th className="px-3 py-2 font-medium">Vendor</th>
                                <th className="px-3 py-2 font-medium">Available</th>
                                <th className="px-3 py-2 font-medium">Inbound</th>
                                <th className="px-3 py-2 font-medium">Projected</th>
                                <th className="px-3 py-2 font-medium">Min / Max</th>
                                <th className="px-3 py-2 font-medium">Suggested</th>
                                <th className="px-3 py-2 font-medium">RFQ</th>
                                <th className="px-3 py-2 font-medium">Triggered</th>
                                <th className="px-3 py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {suggestionRows.length === 0 && (
                                <tr>
                                    <td className="px-3 py-6 text-center text-muted-foreground" colSpan={11}>
                                        No replenishment suggestions for the selected status.
                                    </td>
                                </tr>
                            )}
                            {suggestionRows.map((suggestion) => (
                                <tr key={suggestion.id}>
                                    <td className="px-3 py-2">
                                        {suggestion.product_name ?? '-'}
                                        {suggestion.product_sku ? ` (${suggestion.product_sku})` : ''}
                                    </td>
                                    <td className="px-3 py-2">
                                        {suggestion.location_name ?? '-'}
                                        {suggestion.location_code ? ` (${suggestion.location_code})` : ''}
                                    </td>
                                    <td className="px-3 py-2">
                                        {suggestion.can_convert ? (
                                            <select
                                                className="h-9 min-w-44 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                                value={vendorSelections[suggestion.id] ?? suggestion.preferred_vendor_id ?? ''}
                                                onChange={(event) =>
                                                    setVendorSelections((current) => ({
                                                        ...current,
                                                        [suggestion.id]: event.target.value,
                                                    }))
                                                }
                                            >
                                                <option value="">Select vendor</option>
                                                {vendors.map((vendor) => (
                                                    <option key={vendor.id} value={vendor.id}>
                                                        {vendor.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            suggestion.preferred_vendor_name ?? '-'
                                        )}
                                    </td>
                                    <td className="px-3 py-2">{suggestion.available_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">{suggestion.inbound_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">{suggestion.projected_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">
                                        {suggestion.min_quantity.toFixed(4)} / {suggestion.max_quantity.toFixed(4)}
                                    </td>
                                    <td className="px-3 py-2 font-medium">{suggestion.suggested_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">
                                        {suggestion.rfq_id && suggestion.rfq_number ? (
                                            <Link
                                                href={`/company/purchasing/rfqs/${suggestion.rfq_id}/edit`}
                                                className="font-medium text-primary"
                                            >
                                                {suggestion.rfq_number}
                                            </Link>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {suggestion.triggered_at ? new Date(suggestion.triggered_at).toLocaleString() : '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-2">
                                            {suggestion.can_convert && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/company/inventory/reordering/suggestions/${suggestion.id}/convert`,
                                                            {
                                                                partner_id:
                                                                    vendorSelections[suggestion.id]
                                                                    ?? suggestion.preferred_vendor_id
                                                                    ?? '',
                                                            },
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    Create RFQ
                                                </Button>
                                            )}
                                            {suggestion.can_dismiss && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.post(
                                                            `/company/inventory/reordering/suggestions/${suggestion.id}/dismiss`,
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    Dismiss
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-6 rounded-xl border p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-semibold">Active rules</h2>
                        <p className="text-xs text-muted-foreground">
                            Reorder rules calculate projected stock against the target minimum and maximum levels.
                        </p>
                    </div>
                    {permissions.can_manage && (
                        <Button variant="outline" asChild>
                            <Link href="/company/inventory/reordering/create">Add rule</Link>
                        </Button>
                    )}
                </div>

                <div className="mt-4 overflow-x-auto rounded-lg border">
                    <table className="w-full min-w-[1320px] text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Product</th>
                                <th className="px-3 py-2 font-medium">Location</th>
                                <th className="px-3 py-2 font-medium">Vendor</th>
                                <th className="px-3 py-2 font-medium">Min / Max</th>
                                <th className="px-3 py-2 font-medium">Reorder qty</th>
                                <th className="px-3 py-2 font-medium">Available</th>
                                <th className="px-3 py-2 font-medium">Projected</th>
                                <th className="px-3 py-2 font-medium">Suggested</th>
                                <th className="px-3 py-2 font-medium">Lead time</th>
                                <th className="px-3 py-2 font-medium">Last evaluated</th>
                                <th className="px-3 py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rules.length === 0 && (
                                <tr>
                                    <td className="px-3 py-6 text-center text-muted-foreground" colSpan={11}>
                                        No reordering rules configured yet.
                                    </td>
                                </tr>
                            )}
                            {rules.map((rule) => (
                                <tr key={rule.id}>
                                    <td className="px-3 py-2">
                                        {rule.product_name ?? '-'}
                                        {rule.product_sku ? ` (${rule.product_sku})` : ''}
                                    </td>
                                    <td className="px-3 py-2">
                                        {rule.location_name ?? '-'}
                                        {rule.location_code ? ` (${rule.location_code})` : ''}
                                    </td>
                                    <td className="px-3 py-2">{rule.preferred_vendor_name ?? '-'}</td>
                                    <td className="px-3 py-2">
                                        {rule.min_quantity.toFixed(4)} / {rule.max_quantity.toFixed(4)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {rule.reorder_quantity !== null ? rule.reorder_quantity.toFixed(4) : '-'}
                                    </td>
                                    <td className="px-3 py-2">{rule.metrics.available_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">{rule.metrics.projected_quantity.toFixed(4)}</td>
                                    <td className="px-3 py-2">
                                        {rule.metrics.requires_replenishment ? rule.metrics.suggested_quantity.toFixed(4) : '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {rule.lead_time_days !== null ? `${rule.lead_time_days} day(s)` : '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {rule.last_evaluated_at ? new Date(rule.last_evaluated_at).toLocaleString() : '-'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {permissions.can_manage ? (
                                            <div className="flex flex-wrap gap-2">
                                                <Button size="sm" variant="outline" asChild>
                                                    <Link href={`/company/inventory/reordering/${rule.id}/edit`}>Edit</Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(`/company/inventory/reordering/${rule.id}`, {
                                                            preserveScroll: true,
                                                        })
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        ) : (
                                            '-'
                                        )}
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

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-semibold">{value}</p>
        </div>
    );
}
