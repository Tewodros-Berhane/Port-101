<?php

namespace App\Modules\Sales;

use App\Core\Company\Models\Company;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Sales\Events\SalesOrderConfirmed;
use App\Modules\Sales\Events\SalesOrderReadyForInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderWorkflowService
{
    public function __construct(
        private readonly SalesDocumentTotalsService $totalsService,
        private readonly SalesApprovalPolicyService $approvalPolicyService,
        private readonly SalesNumberingService $numberingService,
        private readonly ApprovalQueueService $approvalQueueService,
    ) {}

    public function create(array $attributes, User $actor): SalesOrder
    {
        $companyId = (string) $actor->current_company_id;
        $calculated = $this->totalsService->calculate($attributes['lines']);
        $totals = $calculated['totals'];

        $order = DB::transaction(function () use ($attributes, $calculated, $totals, $companyId, $actor) {
            $order = SalesOrder::create([
                'company_id' => $companyId,
                'quote_id' => $attributes['quote_id'] ?? null,
                'partner_id' => $attributes['partner_id'],
                'order_number' => $this->numberingService->nextOrderNumber($companyId, $actor->id),
                'status' => SalesOrder::STATUS_DRAFT,
                'order_date' => $attributes['order_date'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $this->approvalPolicyService->requiresApproval(
                    companyId: $companyId,
                    amount: $totals['grand_total'],
                ),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ])
                    ->all()
            );

            if ($order->quote_id) {
                SalesQuote::query()
                    ->where('id', $order->quote_id)
                    ->update([
                        'status' => SalesQuote::STATUS_CONFIRMED,
                        'updated_by' => $actor->id,
                    ]);
            }

            return $order;
        });

        $this->syncPendingApprovals($companyId, $actor->id);

        return $order->fresh(['partner:id,name', 'quote:id,quote_number', 'lines.product:id,name,sku', 'approvedBy:id,name', 'confirmedBy:id,name']);
    }

    public function update(SalesOrder $order, array $attributes, User $actor): SalesOrder
    {
        $companyId = (string) $order->company_id;
        $calculated = $this->totalsService->calculate($attributes['lines']);
        $totals = $calculated['totals'];

        DB::transaction(function () use ($order, $attributes, $calculated, $totals, $companyId, $actor): void {
            $requiresApproval = $this->approvalPolicyService->requiresApproval(
                companyId: $companyId,
                amount: $totals['grand_total'],
            );

            $resetApproval = $order->approved_at !== null && $requiresApproval;

            $order->update([
                'partner_id' => $attributes['partner_id'],
                'order_date' => $attributes['order_date'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $requiresApproval,
                'approved_by' => $resetApproval ? null : $order->approved_by,
                'approved_at' => $resetApproval ? null : $order->approved_at,
                'updated_by' => $actor->id,
            ]);

            $order->lines()->delete();
            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ])
                    ->all()
            );
        });

        $this->syncPendingApprovals($companyId, $actor->id);

        return $order->fresh(['partner:id,name', 'quote:id,quote_number', 'lines.product:id,name,sku', 'approvedBy:id,name', 'confirmedBy:id,name']);
    }

    public function approve(SalesOrder $order, User $actor): SalesOrder
    {
        if ($order->status !== SalesOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'order' => 'Only draft orders can be approved.',
            ]);
        }

        $order->update([
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'updated_by' => $actor->id,
        ]);

        $this->syncPendingApprovals((string) $order->company_id, $actor->id);

        return $order->fresh(['partner:id,name', 'quote:id,quote_number', 'lines.product:id,name,sku', 'approvedBy:id,name', 'confirmedBy:id,name']);
    }

    public function confirm(SalesOrder $order, User $actor): SalesOrder
    {
        if ($order->requires_approval && ! $order->approved_at) {
            throw ValidationException::withMessages([
                'order' => 'This order requires manager approval before confirmation.',
            ]);
        }

        if ($order->status === SalesOrder::STATUS_CONFIRMED) {
            return $order->fresh(['partner:id,name', 'quote:id,quote_number', 'lines.product:id,name,sku', 'approvedBy:id,name', 'confirmedBy:id,name']);
        }

        DB::transaction(function () use ($order, $actor): void {
            $order->update([
                'status' => SalesOrder::STATUS_CONFIRMED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            if ($order->quote_id) {
                SalesQuote::query()
                    ->where('id', $order->quote_id)
                    ->update([
                        'status' => SalesQuote::STATUS_CONFIRMED,
                        'updated_by' => $actor->id,
                    ]);
            }
        });

        event(new SalesOrderConfirmed(
            orderId: $order->id,
            companyId: $order->company_id,
            quoteId: $order->quote_id,
        ));

        event(new SalesOrderReadyForInvoice(
            orderId: $order->id,
            companyId: $order->company_id,
            quoteId: $order->quote_id,
        ));

        $this->syncPendingApprovals((string) $order->company_id, $actor->id);

        return $order->fresh(['partner:id,name', 'quote:id,quote_number', 'lines.product:id,name,sku', 'approvedBy:id,name', 'confirmedBy:id,name']);
    }

    public function delete(SalesOrder $order, ?string $actorId = null): void
    {
        $companyId = (string) $order->company_id;
        $order->lines()->delete();
        $order->delete();

        $this->syncPendingApprovals($companyId, $actorId);
    }

    private function syncPendingApprovals(string $companyId, ?string $actorId): void
    {
        $company = Company::query()->find($companyId);

        if (! $company) {
            return;
        }

        $this->approvalQueueService->syncPendingRequests($company, $actorId);
    }
}
