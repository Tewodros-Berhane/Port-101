import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';

type InvoiceOption = {
    id: string;
    invoice_number: string;
    partner_name?: string | null;
    balance_due: number;
};

type Props = {
    payment: {
        invoice_id: string;
        payment_date: string;
        amount: number;
        method: string;
        reference: string;
        notes: string;
    };
    invoices: InvoiceOption[];
};

export default function AccountingPaymentCreate({ payment, invoices }: Props) {
    const form = useForm({
        invoice_id: payment.invoice_id,
        payment_date: payment.payment_date,
        amount: payment.amount,
        method: payment.method,
        reference: payment.reference,
        notes: payment.notes,
    });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Company', href: '/company/dashboard' },
                { title: 'Accounting', href: '/company/accounting' },
                { title: 'Payments', href: '/company/accounting/payments' },
                {
                    title: 'Create',
                    href: '/company/accounting/payments/create',
                },
            ]}
        >
            <Head title="New Payment" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold">New payment</h1>
                    <p className="text-sm text-muted-foreground">
                        Create a draft payment against an open invoice.
                    </p>
                </div>
                <Button variant="ghost" asChild>
                    <Link href="/company/accounting/payments">Back</Link>
                </Button>
            </div>

            <form
                className="mt-6 grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/company/accounting/payments');
                }}
            >
                <div className="grid gap-4 rounded-xl border p-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="grid gap-2 xl:col-span-2">
                        <Label htmlFor="invoice_id">Invoice</Label>
                        <select
                            id="invoice_id"
                            className="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                            value={form.data.invoice_id}
                            onChange={(event) => {
                                const invoiceId = event.target.value;
                                form.setData('invoice_id', invoiceId);

                                const selectedInvoice = invoices.find(
                                    (invoice) => invoice.id === invoiceId,
                                );

                                if (selectedInvoice) {
                                    form.setData(
                                        'amount',
                                        selectedInvoice.balance_due,
                                    );
                                }
                            }}
                        >
                            <option value="">Select invoice</option>
                            {invoices.map((invoice) => (
                                <option key={invoice.id} value={invoice.id}>
                                    {invoice.invoice_number}
                                    {invoice.partner_name
                                        ? ` - ${invoice.partner_name}`
                                        : ''}
                                    {` (Balance ${invoice.balance_due.toFixed(2)})`}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.invoice_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payment_date">Payment date</Label>
                        <Input
                            id="payment_date"
                            type="date"
                            value={form.data.payment_date}
                            onChange={(event) =>
                                form.setData('payment_date', event.target.value)
                            }
                        />
                        <InputError message={form.errors.payment_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount</Label>
                        <Input
                            id="amount"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData(
                                    'amount',
                                    Number(event.target.value) || 0,
                                )
                            }
                        />
                        <InputError message={form.errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="method">Method</Label>
                        <Input
                            id="method"
                            value={form.data.method}
                            onChange={(event) =>
                                form.setData('method', event.target.value)
                            }
                            placeholder="Bank transfer, card, cash..."
                        />
                        <InputError message={form.errors.method} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="reference">Reference</Label>
                        <Input
                            id="reference"
                            value={form.data.reference}
                            onChange={(event) =>
                                form.setData('reference', event.target.value)
                            }
                            placeholder="Txn ID, remittance reference..."
                        />
                        <InputError message={form.errors.reference} />
                    </div>

                    <div className="grid gap-2 xl:col-span-3">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Create payment
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
