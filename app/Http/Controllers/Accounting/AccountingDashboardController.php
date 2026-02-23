<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->hasPermission('accounting.invoices.view')
                || $user?->hasPermission('accounting.payments.view'),
            403
        );

        $invoiceQuery = AccountingInvoice::query();
        $paymentQuery = AccountingPayment::query();

        if ($user) {
            $invoiceQuery = $user->applyDataScopeToQuery($invoiceQuery);
            $paymentQuery = $user->applyDataScopeToQuery($paymentQuery);
        }

        $recentInvoices = (clone $invoiceQuery)
            ->with(['partner:id,name', 'salesOrder:id,order_number'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (AccountingInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'document_type' => $invoice->document_type,
                'status' => $invoice->status,
                'delivery_status' => $invoice->delivery_status,
                'partner_name' => $invoice->partner?->name,
                'sales_order_number' => $invoice->salesOrder?->order_number,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'balance_due' => (float) $invoice->balance_due,
            ])
            ->values()
            ->all();

        $recentPayments = (clone $paymentQuery)
            ->with(['invoice:id,invoice_number,partner_id', 'invoice.partner:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (AccountingPayment $payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'status' => $payment->status,
                'invoice_number' => $payment->invoice?->invoice_number,
                'partner_name' => $payment->invoice?->partner?->name,
                'payment_date' => $payment->payment_date?->toDateString(),
                'amount' => (float) $payment->amount,
            ])
            ->values()
            ->all();

        return Inertia::render('accounting/index', [
            'kpis' => [
                'draft_invoices' => (clone $invoiceQuery)
                    ->where('status', AccountingInvoice::STATUS_DRAFT)
                    ->count(),
                'posted_invoices' => (clone $invoiceQuery)
                    ->whereIn('status', [
                        AccountingInvoice::STATUS_POSTED,
                        AccountingInvoice::STATUS_PARTIALLY_PAID,
                    ])
                    ->count(),
                'overdue_invoices' => (clone $invoiceQuery)
                    ->whereIn('status', [
                        AccountingInvoice::STATUS_POSTED,
                        AccountingInvoice::STATUS_PARTIALLY_PAID,
                    ])
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->where('balance_due', '>', 0)
                    ->count(),
                'open_receivables' => round((float) (clone $invoiceQuery)
                    ->where('document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE)
                    ->whereIn('status', [
                        AccountingInvoice::STATUS_POSTED,
                        AccountingInvoice::STATUS_PARTIALLY_PAID,
                    ])
                    ->sum('balance_due'), 2),
                'posted_payments_30d' => (clone $paymentQuery)
                    ->whereIn('status', [
                        AccountingPayment::STATUS_POSTED,
                        AccountingPayment::STATUS_RECONCILED,
                    ])
                    ->whereDate('payment_date', '>=', now()->subDays(30)->toDateString())
                    ->count(),
                'reconciled_payments_30d' => (clone $paymentQuery)
                    ->where('status', AccountingPayment::STATUS_RECONCILED)
                    ->where('reconciled_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
        ]);
    }
}
