import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DestructiveConfirmDialog } from '@/components/feedback/destructive-confirm-dialog';
import { ReasonDialog } from '@/components/feedback/reason-dialog';
import InputError from '@/components/input-error';
import { BackLinkAction } from '@/components/navigation/back-link-action';
import SalesLineItemsEditor, {
    type SalesLineItem,
} from '@/components/sales/line-items-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { companyModuleBreadcrumbs, companyModuleLinks } from '@/lib/page-navigation';

type Option = {
    id: string;
    name?: string;
    title?: string;
};

type ProductOption = {
    id: string;
    name: string;
    sku?: string | null;
};

type Quote = {
    id: string;
    lead_id?: string | null;
    partner_id?: string | null;
    quote_number: string;
    status: string;
    quote_date: string;
    valid_until?: string | null;
    subtotal: number;
    discount_total: number;
    tax_total: number;
    grand_total: number;
    requires_approval: boolean;
    approved_at?: string | null;
    order_id?: string | null;
    lines: SalesLineItem[];
};

type Props = {
    quote: Quote;
    leads: Option[];
    partners: Option[];
    products: ProductOption[];
};

export default function SalesQuoteEdit({
    quote,
    leads,
    partners,
    products,
}: Props) {
    const { hasPermission } = usePermissions();
    const canManage = hasPermission('sales.quotes.manage');
    const canApprove = hasPermission('sales.quotes.approve');

    const form = useForm({
        lead_id: quote.lead_id ?? '',
        partner_id: quote.partner_id ?? '',
        quote_date: quote.quote_date,
        valid_until: quote.valid_until ?? '',
        lines: quote.lines,
    });

    const actionForm = useForm({
        reason: '',
    });

    const isConfirmed = quote.status === 'confirmed';
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

    const closeRejectDialog = (open: boolean) => {
        if (actionForm.processing) {
            return;
        }

        if (!open) {
            actionForm.reset();
            actionForm.clearErrors();
            setRejectDialogOpen(false);
        }
    };

    const submitRejection = () => {
        actionForm.post(`/company/sales/quotes/${quote.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                actionForm.reset();
                actionForm.clearErrors();
                setRejectDialogOpen(false);
            },
        });
    };

    const closeDeleteDialog = (open: boolean) => {
        if (form.processing) {
            return;
        }

        if (!open) {
            setDeleteDialogOpen(false);
        }
    };

    const submitDelete = () => {
        form.delete(`/company/sales/quotes/${quote.id}`, {
            onSuccess: () => {
                setDeleteDialogOpen(false);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={companyModuleBreadcrumbs(companyModuleLinks.sales, { title: 'Quotes', href: '/company/sales/quotes' },
                {
                    title: quote.quote_number,
                    href: `/company/sales/quotes/${quote.id}/edit`,
                },)}
        >
            <Head title={quote.quote_number} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">Edit quote</h1>
                    <p className="text-sm text-muted-foreground">
                        {quote.quote_number} - {quote.status}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <BackLinkAction href="/company/sales/quotes" label="Back to quotes" variant="ghost" />
                    {quote.order_id && (
                        <Button variant="outline" asChild>
                            <Link href={`/company/sales/orders/${quote.order_id}/edit`}>
                                Open order
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            {quote.requires_approval && !quote.approved_at && (
                <div className="mt-6 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                    This quote requires manager approval before confirmation.
                </div>
            )}

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/company/sales/quotes/${quote.id}`);
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="lead_id">Lead</Label>
                        <select
                            id="lead_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.lead_id}
                            onChange={(event) =>
                                form.setData('lead_id', event.target.value)
                            }
                            disabled={form.processing || isConfirmed}
                        >
                            <option value="">No linked lead</option>
                            {leads.map((lead) => (
                                <option key={lead.id} value={lead.id}>
                                    {lead.title}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.lead_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="partner_id">Partner</Label>
                        <select
                            id="partner_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.partner_id}
                            onChange={(event) =>
                                form.setData('partner_id', event.target.value)
                            }
                            required
                            disabled={form.processing || isConfirmed}
                        >
                            <option value="">Select partner</option>
                            {partners.map((partner) => (
                                <option key={partner.id} value={partner.id}>
                                    {partner.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.partner_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="quote_date">Quote date</Label>
                        <Input
                            id="quote_date"
                            type="date"
                            value={form.data.quote_date}
                            onChange={(event) =>
                                form.setData('quote_date', event.target.value)
                            }
                            required
                            disabled={form.processing || isConfirmed}
                        />
                        <InputError message={form.errors.quote_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="valid_until">Valid until</Label>
                        <Input
                            id="valid_until"
                            type="date"
                            value={form.data.valid_until}
                            onChange={(event) =>
                                form.setData('valid_until', event.target.value)
                            }
                            disabled={form.processing || isConfirmed}
                        />
                        <InputError message={form.errors.valid_until} />
                    </div>
                </div>

                <SalesLineItemsEditor
                    lines={form.data.lines}
                    products={products}
                    errors={form.errors as Record<string, string | undefined>}
                    onChange={(lines) => form.setData('lines', lines)}
                    disabled={form.processing || isConfirmed}
                />

                <div className="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-4">
                    <div>
                        <p className="text-xs text-muted-foreground">Subtotal</p>
                        <p className="font-semibold">
                            {quote.subtotal.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Discount</p>
                        <p className="font-semibold">
                            {quote.discount_total.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Tax</p>
                        <p className="font-semibold">
                            {quote.tax_total.toFixed(2)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Total</p>
                        <p className="font-semibold">
                            {quote.grand_total.toFixed(2)}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canManage && !isConfirmed && (
                        <Button type="submit" disabled={form.processing}>
                            Save changes
                        </Button>
                    )}

                    {canManage && !isConfirmed && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/send`,
                                )
                            }
                        >
                            Mark sent
                        </Button>
                    )}

                    {canApprove && !isConfirmed && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/approve`,
                                )
                            }
                        >
                            Approve
                        </Button>
                    )}

                    {canApprove && !isConfirmed && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={actionForm.processing}
                            onClick={() => {
                                actionForm.setData('reason', '');
                                actionForm.clearErrors();
                                setRejectDialogOpen(true);
                            }}
                        >
                            Reject
                        </Button>
                    )}

                    {canManage && !isConfirmed && (
                        <Button
                            type="button"
                            disabled={actionForm.processing}
                            onClick={() =>
                                actionForm.post(
                                    `/company/sales/quotes/${quote.id}/confirm`,
                                )
                            }
                        >
                            Confirm quote
                        </Button>
                    )}

                    {canManage && !isConfirmed && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => setDeleteDialogOpen(true)}
                            disabled={form.processing}
                        >
                            Delete
                        </Button>
                    )}
                </div>
            </form>

            <ReasonDialog
                open={rejectDialogOpen}
                onOpenChange={closeRejectDialog}
                tone="warning"
                title="Reject quote"
                description={
                    <>
                        Rejecting <span className="font-medium">{quote.quote_number}</span>{' '}
                        will move it back out of the approval path.
                    </>
                }
                confirmLabel="Reject quote"
                processingLabel="Rejecting..."
                reason={actionForm.data.reason}
                onReasonChange={(value) => actionForm.setData('reason', value)}
                reasonLabel="Rejection reason"
                reasonPlaceholder="Add an optional note for the sales team"
                reasonHelperText="This note is optional and will be stored with the quote rejection."
                reasonError={actionForm.errors.reason}
                errors={actionForm.errors}
                onConfirm={submitRejection}
                processing={actionForm.processing}
            />

            <DestructiveConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={closeDeleteDialog}
                title="Delete quote"
                description={
                    <>
                        Delete <span className="font-medium">{quote.quote_number}</span> and
                        remove its current line items.
                    </>
                }
                confirmLabel="Delete quote"
                processingLabel="Deleting..."
                helperText="Use this only when the quote should be removed entirely. This cannot be undone from the quote screen."
                onConfirm={submitDelete}
                processing={form.processing}
            />
        </AppLayout>
    );
}
