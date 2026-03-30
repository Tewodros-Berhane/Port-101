import { Head } from '@inertiajs/react';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import { DetailHero } from '@/components/shell/detail-hero';
import { TabbedDetailShell } from '@/components/shell/tabbed-detail-shell';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type HistoryRow = {
    id: string;
    move_id: string;
    reference?: string | null;
    move_type?: string | null;
    status?: string | null;
    source_location_name?: string | null;
    destination_location_name?: string | null;
    quantity: number;
    direction: string;
    created_at?: string | null;
};

type Props = {
    lot: {
        id: string;
        code: string;
        tracking_mode: string;
        product_name?: string | null;
        product_sku?: string | null;
        location_name?: string | null;
        location_type?: string | null;
        quantity_on_hand: number;
        quantity_reserved: number;
        available_quantity: number;
        received_at?: string | null;
        last_moved_at?: string | null;
    };
    history: HistoryRow[];
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : '-';

const formatTrackingLabel = (value: string) =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());

export default function InventoryLotShow({ lot, history }: Props) {
    const tabs = [
        {
            id: 'overview',
            label: 'Overview',
            content: (
                <Card>
                    <CardHeader>
                        <CardTitle>Tracked unit overview</CardTitle>
                        <CardDescription>
                            Inventory identity, storage location, and traceability timestamps for this lot or serial.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <DetailField label="Product" value={lot.product_name ?? '-'} />
                            <DetailField label="SKU" value={lot.product_sku ?? '-'} />
                            <DetailField
                                label="Tracking mode"
                                value={formatTrackingLabel(lot.tracking_mode)}
                            />
                            <DetailField label="Location" value={lot.location_name ?? '-'} />
                            <DetailField label="Location type" value={lot.location_type ?? '-'} />
                            <DetailField label="Received" value={formatDateTime(lot.received_at)} />
                            <DetailField label="Last moved" value={formatDateTime(lot.last_moved_at)} />
                        </div>
                    </CardContent>
                </Card>
            ),
        },
        {
            id: 'movement-history',
            label: 'Movement History',
            content: (
                <Card>
                    <CardHeader>
                        <CardTitle>Movement history</CardTitle>
                        <CardDescription>
                            Receipt, delivery, and transfer activity recorded against this tracked unit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table className="min-w-[920px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Direction</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>From</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead className="text-right">Qty</TableHead>
                                    <TableHead>Created</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {history.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            No movement history yet.
                                        </TableCell>
                                    </TableRow>
                                )}

                                {history.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>{row.reference ?? '-'}</TableCell>
                                        <TableCell className="capitalize">
                                            {row.direction}
                                        </TableCell>
                                        <TableCell className="capitalize">
                                            {row.move_type ?? '-'}
                                        </TableCell>
                                        <TableCell className="capitalize">
                                            {row.status ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.source_location_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.destination_location_name ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {row.quantity.toFixed(4)}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateTime(row.created_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.inventory, { title: 'Lots & Serials', href: '/company/inventory/lots' },
                { title: lot.code, href: `/company/inventory/lots/${lot.id}` },)}
        >
            <Head title={lot.code} />

            <TabbedDetailShell
                hero={
                    <DetailHero
                        title={lot.code}
                        description="Traceability view for a tracked lot or serial record."
                        status={
                            <Badge variant="neutral">
                                {formatTrackingLabel(lot.tracking_mode)}
                            </Badge>
                        }
                        meta={
                            <>
                                <span>{lot.product_name ?? '-'}</span>
                                {lot.product_sku && (
                                    <>
                                        <span>|</span>
                                        <span>{lot.product_sku}</span>
                                    </>
                                )}
                                <span>|</span>
                                <span>{lot.location_name ?? '-'}</span>
                            </>
                        }
                        actions={
                            <BackLinkAction href="/company/inventory/lots" label="Back to lots & serials
                                " variant="outline" />
                        }
                        metrics={[
                            {
                                label: 'On hand',
                                value: lot.quantity_on_hand.toFixed(4),
                            },
                            {
                                label: 'Reserved',
                                value: lot.quantity_reserved.toFixed(4),
                            },
                            {
                                label: 'Available',
                                value: lot.available_quantity.toFixed(4),
                                tone:
                                    lot.available_quantity > 0
                                        ? 'success'
                                        : 'warning',
                            },
                            {
                                label: 'Last moved',
                                value: lot.last_moved_at
                                    ? new Date(lot.last_moved_at).toLocaleDateString()
                                    : '-',
                            },
                        ]}
                    />
                }
                tabs={tabs}
                defaultTab="overview"
            />
        </AppLayout>
    );
}

function DetailField({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-[var(--radius-panel)] border border-[color:var(--border-subtle)] bg-[color:var(--bg-surface-muted)] px-4 py-3">
            <p className="text-[11px] font-semibold tracking-[0.08em] text-[color:var(--text-secondary)] uppercase">
                {label}
            </p>
            <p className="mt-2 text-sm font-medium text-foreground">{value}</p>
        </div>
    );
}
