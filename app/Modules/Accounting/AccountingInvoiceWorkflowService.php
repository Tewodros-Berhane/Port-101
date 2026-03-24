<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Integrations\OutboundEventService;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class AccountingInvoiceWorkflowService
{
    public function __construct(
        private readonly AccountingTotalsService $totalsService,
        private readonly AccountingNumberingService $numberingService,
        private readonly AccountingPeriodGuardService $periodGuardService,
        private readonly AccountingLedgerPostingService $ledgerPostingService,
        private readonly OutboundEventService $outboundEventService,
    ) {}

    /**
     * @param  array{
     *     partner_id: string,
     *     document_type?: string,
     *     sales_order_id?: string|null,
     *     invoice_date?: string|null,
     *     due_date?: string|null,
     *     currency_code?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $attributes
     */
    public function createDraft(
        array $attributes,
        string $companyId,
        ?string $actorId = null
    ): AccountingInvoice {
        $documentType = (string) ($attributes['document_type'] ?? AccountingInvoice::TYPE_CUSTOMER_INVOICE);
        $calculated = $this->totalsService->calculate($attributes['lines'] ?? []);
        $totals = $calculated['totals'];

        return DB::transaction(function () use (
            $attributes,
            $documentType,
            $calculated,
            $totals,
            $companyId,
            $actorId
        ) {
            $deliveryStatus = $this->resolveDeliveryStatus(
                documentType: $documentType,
                salesOrderId: $attributes['sales_order_id'] ?? null,
            );

            $invoice = AccountingInvoice::create([
                'company_id' => $companyId,
                'external_reference' => $attributes['external_reference'] ?? null,
                'partner_id' => $attributes['partner_id'],
                'sales_order_id' => $attributes['sales_order_id'] ?? null,
                'document_type' => $documentType,
                'invoice_number' => $this->numberingService->nextInvoiceNumber(
                    companyId: $companyId,
                    documentType: $documentType,
                    actorId: $actorId,
                ),
                'status' => AccountingInvoice::STATUS_DRAFT,
                'delivery_status' => $deliveryStatus,
                'invoice_date' => $attributes['invoice_date'] ?? now()->toDateString(),
                'due_date' => $attributes['due_date'] ?? null,
                'currency_code' => $attributes['currency_code'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'paid_total' => 0,
                'balance_due' => $totals['grand_total'],
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $invoice->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ])
                    ->all()
            );

            return $invoice->fresh(['lines']);
        });
    }

    /**
     * @param  array{
     *     partner_id: string,
     *     document_type?: string,
     *     invoice_date?: string|null,
     *     due_date?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $attributes
     */
    public function updateDraft(
        AccountingInvoice $invoice,
        array $attributes,
        ?string $actorId = null
    ): AccountingInvoice {
        return DB::transaction(function () use ($invoice, $attributes, $actorId) {
            $invoice = AccountingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== AccountingInvoice::STATUS_DRAFT) {
                abort(422, 'Only draft invoices can be updated.');
            }

            $documentType = (string) ($attributes['document_type'] ?? $invoice->document_type);
            $calculated = $this->totalsService->calculate($attributes['lines'] ?? []);
            $totals = $calculated['totals'];

            $invoice->update([
                'external_reference' => $attributes['external_reference'] ?? null,
                'partner_id' => $attributes['partner_id'],
                'document_type' => $documentType,
                'delivery_status' => $this->resolveDeliveryStatus(
                    documentType: $documentType,
                    salesOrderId: $invoice->sales_order_id,
                    currentDeliveryStatus: (string) $invoice->delivery_status,
                ),
                'invoice_date' => $attributes['invoice_date'] ?? $invoice->invoice_date?->toDateString(),
                'due_date' => $attributes['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'paid_total' => 0,
                'balance_due' => $totals['grand_total'],
                'notes' => $attributes['notes'] ?? null,
                'updated_by' => $actorId,
            ]);

            $invoice->lines()->delete();
            $invoice->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => (string) $invoice->company_id,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ])
                    ->all()
            );

            return $invoice->fresh(['lines']);
        });
    }

    public function post(AccountingInvoice $invoice, ?string $actorId = null): AccountingInvoice
    {
        $wasPosted = false;

        $invoice = DB::transaction(function () use ($invoice, $actorId, &$wasPosted) {
            $invoice = AccountingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (in_array($invoice->status, [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
                AccountingInvoice::STATUS_PAID,
            ], true)) {
                return $invoice;
            }

            if ($invoice->status === AccountingInvoice::STATUS_CANCELLED) {
                abort(422, 'Cancelled invoices cannot be posted.');
            }

            $this->periodGuardService->assertPostingAllowed(
                companyId: (string) $invoice->company_id,
                postingDate: $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
            );

            if (
                $invoice->document_type === AccountingInvoice::TYPE_CUSTOMER_INVOICE
                && $invoice->delivery_status === AccountingInvoice::DELIVERY_STATUS_PENDING
            ) {
                abort(422, 'Customer invoice cannot be posted until delivery is marked ready.');
            }

            $status = (float) $invoice->balance_due <= 0
                ? AccountingInvoice::STATUS_PAID
                : AccountingInvoice::STATUS_POSTED;

            $invoice->update([
                'status' => $status,
                'posted_at' => now(),
                'posted_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $wasPosted = true;

            $this->ledgerPostingService->postInvoice($invoice, $actorId);
            $this->syncSalesOrderStatus($invoice, $actorId);

            return $invoice->fresh();
        });

        if ($wasPosted) {
            $invoice->load([
                'partner:id,name',
                'salesOrder:id,order_number',
                'purchaseOrder:id,order_number',
            ]);

            $this->outboundEventService->record(
                companyId: (string) $invoice->company_id,
                eventType: WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
                aggregateType: AccountingInvoice::class,
                aggregateId: (string) $invoice->id,
                data: [
                    'object_type' => 'accounting_invoice',
                    'object_id' => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'status' => (string) $invoice->status,
                    'document_type' => (string) $invoice->document_type,
                    'partner_id' => $invoice->partner_id ? (string) $invoice->partner_id : null,
                    'partner_name' => $invoice->partner?->name,
                    'sales_order_id' => $invoice->sales_order_id ? (string) $invoice->sales_order_id : null,
                    'sales_order_number' => $invoice->salesOrder?->order_number,
                    'purchase_order_id' => $invoice->purchase_order_id ? (string) $invoice->purchase_order_id : null,
                    'purchase_order_number' => $invoice->purchaseOrder?->order_number,
                    'invoice_date' => $invoice->invoice_date?->toDateString(),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'currency_code' => $invoice->currency_code,
                    'grand_total' => (float) $invoice->grand_total,
                    'balance_due' => (float) $invoice->balance_due,
                    'posted_at' => $invoice->posted_at?->toIso8601String(),
                ],
                actorId: $actorId,
            );
        }

        return $invoice;
    }

    public function cancel(AccountingInvoice $invoice, ?string $actorId = null): AccountingInvoice
    {
        return DB::transaction(function () use ($invoice, $actorId) {
            $invoice = AccountingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === AccountingInvoice::STATUS_CANCELLED) {
                return $invoice;
            }

            if (in_array($invoice->status, [
                AccountingInvoice::STATUS_PARTIALLY_PAID,
                AccountingInvoice::STATUS_PAID,
            ], true)) {
                abort(422, 'Paid invoices cannot be cancelled.');
            }

            $invoice->update([
                'status' => AccountingInvoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            if ($invoice->posted_at) {
                $this->ledgerPostingService->reverseInvoice(
                    invoice: $invoice,
                    reason: 'Invoice cancelled.',
                    actorId: $actorId,
                );
            }

            return $invoice->fresh();
        });
    }

    public function createOrRefreshFromSalesOrder(string $companyId, string $orderId): ?AccountingInvoice
    {
        $order = SalesOrder::query()
            ->with(['company:id,currency_code', 'lines.product:id,type'])
            ->where('company_id', $companyId)
            ->find($orderId);

        if (! $order) {
            return null;
        }

        if ($order->lines->isEmpty()) {
            return null;
        }

        $linePayload = $order->lines
            ->map(fn ($line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
            ])
            ->values()
            ->all();

        $calculated = $this->totalsService->calculate($linePayload);
        $totals = $calculated['totals'];
        $requiresDelivery = $order->lines
            ->contains(fn ($line) => ($line->product?->type ?? null) === 'stock');

        return DB::transaction(function () use (
            $order,
            $companyId,
            $calculated,
            $totals,
            $requiresDelivery
        ) {
            $invoice = AccountingInvoice::query()
                ->lockForUpdate()
                ->where('company_id', $companyId)
                ->where('sales_order_id', $order->id)
                ->where('document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE)
                ->first();

            if ($invoice && $invoice->status !== AccountingInvoice::STATUS_DRAFT) {
                return $invoice;
            }

            $deliveryStatus = $requiresDelivery
                ? AccountingInvoice::DELIVERY_STATUS_PENDING
                : AccountingInvoice::DELIVERY_STATUS_READY;

            if (! $invoice) {
                $invoice = AccountingInvoice::create([
                    'company_id' => $companyId,
                    'partner_id' => $order->partner_id,
                    'sales_order_id' => $order->id,
                    'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
                    'invoice_number' => $this->numberingService->nextInvoiceNumber(
                        companyId: $companyId,
                        documentType: AccountingInvoice::TYPE_CUSTOMER_INVOICE,
                        actorId: $order->created_by,
                    ),
                    'status' => AccountingInvoice::STATUS_DRAFT,
                    'delivery_status' => $deliveryStatus,
                    'invoice_date' => $order->order_date?->toDateString() ?? now()->toDateString(),
                    'due_date' => $order->order_date?->copy()->addDays(30)->toDateString(),
                    'currency_code' => $order->company?->currency_code,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'paid_total' => 0,
                    'balance_due' => $totals['grand_total'],
                    'notes' => 'Auto-generated from sales order '.$order->order_number,
                    'created_by' => $order->created_by,
                    'updated_by' => $order->updated_by ?? $order->created_by,
                ]);
            } else {
                $invoice->update([
                    'partner_id' => $order->partner_id,
                    'delivery_status' => $invoice->delivery_status === AccountingInvoice::DELIVERY_STATUS_READY
                        ? AccountingInvoice::DELIVERY_STATUS_READY
                        : $deliveryStatus,
                    'invoice_date' => $order->order_date?->toDateString() ?? $invoice->invoice_date?->toDateString(),
                    'due_date' => $order->order_date?->copy()->addDays(30)->toDateString(),
                    'currency_code' => $order->company?->currency_code,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'paid_total' => 0,
                    'balance_due' => $totals['grand_total'],
                    'notes' => 'Auto-generated from sales order '.$order->order_number,
                    'updated_by' => $order->updated_by ?? $order->created_by,
                ]);

                $invoice->lines()->delete();
            }

            $invoice->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $order->created_by,
                        'updated_by' => $order->updated_by ?? $order->created_by,
                    ])
                    ->all()
            );

            return $invoice->fresh(['lines']);
        });
    }

    public function createOrRefreshVendorBillFromPurchaseOrder(
        string $companyId,
        string $orderId
    ): ?AccountingInvoice {
        $order = PurchaseOrder::query()
            ->with(['company:id,currency_code', 'lines'])
            ->where('company_id', $companyId)
            ->find($orderId);

        if (! $order) {
            return null;
        }

        $billableLines = $order->lines
            ->filter(fn ($line) => (float) $line->received_quantity > 0);

        if ($billableLines->isEmpty()) {
            return null;
        }

        $linePayload = $billableLines
            ->map(fn ($line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->received_quantity,
                'unit_price' => (float) $line->unit_cost,
                'tax_rate' => (float) $line->tax_rate,
            ])
            ->values()
            ->all();

        $calculated = $this->totalsService->calculate($linePayload);
        $totals = $calculated['totals'];

        return DB::transaction(function () use ($order, $companyId, $calculated, $totals) {
            $lockedOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->where('company_id', $companyId)
                ->findOrFail($order->id);

            $invoice = AccountingInvoice::query()
                ->lockForUpdate()
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $lockedOrder->id)
                ->where('document_type', AccountingInvoice::TYPE_VENDOR_BILL)
                ->first();

            if ($invoice && $invoice->status !== AccountingInvoice::STATUS_DRAFT) {
                return $invoice;
            }

            if (! $invoice) {
                $invoice = AccountingInvoice::create([
                    'company_id' => $companyId,
                    'partner_id' => $lockedOrder->partner_id,
                    'sales_order_id' => null,
                    'purchase_order_id' => $lockedOrder->id,
                    'document_type' => AccountingInvoice::TYPE_VENDOR_BILL,
                    'invoice_number' => $this->numberingService->nextInvoiceNumber(
                        companyId: $companyId,
                        documentType: AccountingInvoice::TYPE_VENDOR_BILL,
                        actorId: $lockedOrder->created_by,
                    ),
                    'status' => AccountingInvoice::STATUS_DRAFT,
                    'delivery_status' => AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED,
                    'invoice_date' => $lockedOrder->order_date?->toDateString() ?? now()->toDateString(),
                    'due_date' => $lockedOrder->order_date?->copy()->addDays(30)->toDateString(),
                    'currency_code' => $lockedOrder->company?->currency_code,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'paid_total' => 0,
                    'balance_due' => $totals['grand_total'],
                    'notes' => 'Auto-generated from purchase order '.$lockedOrder->order_number,
                    'created_by' => $lockedOrder->created_by,
                    'updated_by' => $lockedOrder->updated_by ?? $lockedOrder->created_by,
                ]);
            } else {
                $invoice->update([
                    'partner_id' => $lockedOrder->partner_id,
                    'invoice_date' => $lockedOrder->order_date?->toDateString() ?? $invoice->invoice_date?->toDateString(),
                    'due_date' => $lockedOrder->order_date?->copy()->addDays(30)->toDateString(),
                    'currency_code' => $lockedOrder->company?->currency_code,
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'paid_total' => 0,
                    'balance_due' => $totals['grand_total'],
                    'notes' => 'Auto-generated from purchase order '.$lockedOrder->order_number,
                    'updated_by' => $lockedOrder->updated_by ?? $lockedOrder->created_by,
                ]);

                $invoice->lines()->delete();
            }

            $invoice->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $lockedOrder->created_by,
                        'updated_by' => $lockedOrder->updated_by ?? $lockedOrder->created_by,
                    ])
                    ->all()
            );

            if ($lockedOrder->status === PurchaseOrder::STATUS_RECEIVED) {
                $lockedOrder->update([
                    'status' => PurchaseOrder::STATUS_BILLED,
                    'billed_at' => now(),
                    'updated_by' => $lockedOrder->updated_by ?? $lockedOrder->created_by,
                ]);
            }

            return $invoice->fresh(['lines']);
        });
    }

    public function markDeliveryReadyForSalesOrder(
        string $companyId,
        string $orderId,
        ?string $actorId = null
    ): void {
        DB::transaction(function () use ($companyId, $orderId, $actorId): void {
            $allDeliveryMovesCompleted = \App\Modules\Inventory\Models\InventoryStockMove::query()
                ->where('company_id', $companyId)
                ->where('related_sales_order_id', $orderId)
                ->where('move_type', \App\Modules\Inventory\Models\InventoryStockMove::TYPE_DELIVERY)
                ->exists()
                && ! \App\Modules\Inventory\Models\InventoryStockMove::query()
                    ->where('company_id', $companyId)
                    ->where('related_sales_order_id', $orderId)
                    ->where('move_type', \App\Modules\Inventory\Models\InventoryStockMove::TYPE_DELIVERY)
                    ->where('status', '!=', \App\Modules\Inventory\Models\InventoryStockMove::STATUS_DONE)
                    ->exists();

            if (! $allDeliveryMovesCompleted) {
                return;
            }

            AccountingInvoice::query()
                ->where('company_id', $companyId)
                ->where('sales_order_id', $orderId)
                ->where('document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE)
                ->where('delivery_status', AccountingInvoice::DELIVERY_STATUS_PENDING)
                ->update([
                    'delivery_status' => AccountingInvoice::DELIVERY_STATUS_READY,
                    'updated_by' => $actorId,
                ]);

            SalesOrder::query()
                ->where('company_id', $companyId)
                ->where('id', $orderId)
                ->where('status', SalesOrder::STATUS_CONFIRMED)
                ->update([
                    'status' => SalesOrder::STATUS_FULFILLED,
                    'updated_by' => $actorId,
                ]);
        });
    }

    public function applyReconciledAmount(
        AccountingInvoice $invoice,
        float $amount,
        ?string $actorId = null
    ): AccountingInvoice {
        if ($amount <= 0) {
            abort(422, 'Reconciled amount must be greater than zero.');
        }

        return DB::transaction(function () use ($invoice, $amount, $actorId) {
            $invoice = AccountingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === AccountingInvoice::STATUS_CANCELLED) {
                abort(422, 'Cancelled invoices cannot receive payments.');
            }

            $nextPaid = round((float) $invoice->paid_total + $amount, 2);
            $grandTotal = (float) $invoice->grand_total;

            if ($nextPaid > ($grandTotal + 0.01)) {
                abort(422, 'Reconciled amount exceeds invoice total.');
            }

            $nextBalance = round(max($grandTotal - $nextPaid, 0), 2);
            $nextStatus = $nextBalance <= 0
                ? AccountingInvoice::STATUS_PAID
                : AccountingInvoice::STATUS_PARTIALLY_PAID;

            $invoice->update([
                'paid_total' => $nextPaid,
                'balance_due' => $nextBalance,
                'status' => $nextStatus,
                'updated_by' => $actorId,
            ]);

            $this->syncSalesOrderStatus($invoice, $actorId);

            return $invoice->fresh();
        });
    }

    public function reverseReconciledAmount(
        AccountingInvoice $invoice,
        float $amount,
        ?string $actorId = null
    ): AccountingInvoice {
        if ($amount <= 0) {
            abort(422, 'Reversal amount must be greater than zero.');
        }

        return DB::transaction(function () use ($invoice, $amount, $actorId) {
            $invoice = AccountingInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $nextPaid = round(max((float) $invoice->paid_total - $amount, 0), 2);
            $grandTotal = (float) $invoice->grand_total;
            $nextBalance = round(max($grandTotal - $nextPaid, 0), 2);

            $nextStatus = $invoice->status;

            if ($invoice->status !== AccountingInvoice::STATUS_CANCELLED) {
                if ($nextBalance <= 0) {
                    $nextStatus = AccountingInvoice::STATUS_PAID;
                } elseif ($nextPaid > 0) {
                    $nextStatus = AccountingInvoice::STATUS_PARTIALLY_PAID;
                } elseif ($invoice->posted_at) {
                    $nextStatus = AccountingInvoice::STATUS_POSTED;
                } else {
                    $nextStatus = AccountingInvoice::STATUS_DRAFT;
                }
            }

            $invoice->update([
                'paid_total' => $nextPaid,
                'balance_due' => $nextBalance,
                'status' => $nextStatus,
                'updated_by' => $actorId,
            ]);

            $this->syncSalesOrderStatus($invoice, $actorId);

            return $invoice->fresh();
        });
    }

    private function syncSalesOrderStatus(AccountingInvoice $invoice, ?string $actorId): void
    {
        if (! $invoice->sales_order_id) {
            return;
        }

        if (! in_array($invoice->status, [
            AccountingInvoice::STATUS_POSTED,
            AccountingInvoice::STATUS_PARTIALLY_PAID,
            AccountingInvoice::STATUS_PAID,
        ], true)) {
            return;
        }

        SalesOrder::query()
            ->where('company_id', $invoice->company_id)
            ->where('id', $invoice->sales_order_id)
            ->whereNotIn('status', [
                SalesOrder::STATUS_CANCELLED,
                SalesOrder::STATUS_CLOSED,
            ])
            ->update([
                'status' => SalesOrder::STATUS_INVOICED,
                'updated_by' => $actorId,
            ]);
    }

    private function resolveDeliveryStatus(
        string $documentType,
        ?string $salesOrderId,
        ?string $currentDeliveryStatus = null
    ): string {
        if ($documentType === AccountingInvoice::TYPE_VENDOR_BILL) {
            return AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED;
        }

        if ($currentDeliveryStatus === AccountingInvoice::DELIVERY_STATUS_READY) {
            return AccountingInvoice::DELIVERY_STATUS_READY;
        }

        if ($salesOrderId) {
            return AccountingInvoice::DELIVERY_STATUS_PENDING;
        }

        return AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED;
    }
}
