<?php

namespace App\Modules\Sales;

use App\Core\Company\Models\Company;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQuoteWorkflowService
{
    public function __construct(
        private readonly SalesDocumentTotalsService $totalsService,
        private readonly SalesApprovalPolicyService $approvalPolicyService,
        private readonly SalesNumberingService $numberingService,
        private readonly SalesQuoteConversionService $conversionService,
        private readonly ApprovalQueueService $approvalQueueService,
    ) {}

    public function create(array $attributes, User $actor): SalesQuote
    {
        $companyId = (string) $actor->current_company_id;

        $calculated = $this->totalsService->calculate($attributes['lines']);
        $totals = $calculated['totals'];

        $quote = DB::transaction(function () use ($attributes, $calculated, $totals, $companyId, $actor) {
            $quote = SalesQuote::create([
                'company_id' => $companyId,
                'external_reference' => $attributes['external_reference'] ?? null,
                'lead_id' => $attributes['lead_id'] ?? null,
                'partner_id' => $attributes['partner_id'],
                'quote_number' => $this->numberingService->nextQuoteNumber($companyId, $actor->id),
                'status' => SalesQuote::STATUS_DRAFT,
                'quote_date' => $attributes['quote_date'],
                'valid_until' => $attributes['valid_until'] ?? null,
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

            $quote->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ])
                    ->all()
            );

            if ($quote->lead_id) {
                SalesLead::query()
                    ->where('id', $quote->lead_id)
                    ->update([
                        'stage' => 'quoted',
                        'updated_by' => $actor->id,
                    ]);
            }

            return $quote;
        });

        $this->syncPendingApprovals($companyId, $actor->id);

        return $quote->fresh(['partner:id,name', 'lead:id,title', 'lines.product:id,name,sku', 'approvedBy:id,name', 'order:id,quote_id,order_number']);
    }

    public function update(SalesQuote $quote, array $attributes, User $actor): SalesQuote
    {
        $companyId = (string) $quote->company_id;
        $calculated = $this->totalsService->calculate($attributes['lines']);
        $totals = $calculated['totals'];

        DB::transaction(function () use ($quote, $attributes, $calculated, $totals, $companyId, $actor): void {
            $requiresApproval = $this->approvalPolicyService->requiresApproval(
                companyId: $companyId,
                amount: $totals['grand_total'],
            );

            $resetApproval = $quote->status === SalesQuote::STATUS_APPROVED;

            $quote->update([
                'external_reference' => $attributes['external_reference'] ?? null,
                'lead_id' => $attributes['lead_id'] ?? null,
                'partner_id' => $attributes['partner_id'],
                'quote_date' => $attributes['quote_date'],
                'valid_until' => $attributes['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $requiresApproval,
                'approved_by' => $resetApproval ? null : $quote->approved_by,
                'approved_at' => $resetApproval ? null : $quote->approved_at,
                'status' => $resetApproval
                    ? SalesQuote::STATUS_DRAFT
                    : ($quote->status === SalesQuote::STATUS_REJECTED
                        ? SalesQuote::STATUS_DRAFT
                        : $quote->status),
                'updated_by' => $actor->id,
            ]);

            $quote->lines()->delete();
            $quote->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ])
                    ->all()
            );

            if ($quote->lead_id) {
                SalesLead::query()
                    ->where('id', $quote->lead_id)
                    ->update([
                        'stage' => 'quoted',
                        'updated_by' => $actor->id,
                    ]);
            }
        });

        $this->syncPendingApprovals($companyId, $actor->id);

        return $quote->fresh(['partner:id,name', 'lead:id,title', 'lines.product:id,name,sku', 'approvedBy:id,name', 'order:id,quote_id,order_number']);
    }

    public function send(SalesQuote $quote, User $actor): SalesQuote
    {
        if (! in_array($quote->status, [SalesQuote::STATUS_DRAFT, SalesQuote::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'quote' => 'Only draft or rejected quotes can be sent.',
            ]);
        }

        $quote->update([
            'status' => SalesQuote::STATUS_SENT,
            'rejection_reason' => null,
            'updated_by' => $actor->id,
        ]);

        $this->syncPendingApprovals((string) $quote->company_id, $actor->id);

        return $quote->fresh(['partner:id,name', 'lead:id,title', 'lines.product:id,name,sku', 'approvedBy:id,name', 'order:id,quote_id,order_number']);
    }

    public function approve(SalesQuote $quote, User $actor): SalesQuote
    {
        if ($quote->status === SalesQuote::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'quote' => 'Confirmed quotes cannot be approved again.',
            ]);
        }

        $quote->update([
            'status' => SalesQuote::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'rejection_reason' => null,
            'updated_by' => $actor->id,
        ]);

        $this->syncPendingApprovals((string) $quote->company_id, $actor->id);

        return $quote->fresh(['partner:id,name', 'lead:id,title', 'lines.product:id,name,sku', 'approvedBy:id,name', 'order:id,quote_id,order_number']);
    }

    public function reject(SalesQuote $quote, User $actor, ?string $reason = null): SalesQuote
    {
        if ($quote->status === SalesQuote::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'quote' => 'Confirmed quotes cannot be rejected.',
            ]);
        }

        $quote->update([
            'status' => SalesQuote::STATUS_REJECTED,
            'rejection_reason' => $reason ?: null,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $actor->id,
        ]);

        $this->syncPendingApprovals((string) $quote->company_id, $actor->id);

        return $quote->fresh(['partner:id,name', 'lead:id,title', 'lines.product:id,name,sku', 'approvedBy:id,name', 'order:id,quote_id,order_number']);
    }

    public function confirm(SalesQuote $quote, User $actor): SalesOrder
    {
        if ($quote->requires_approval && $quote->status !== SalesQuote::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'quote' => 'This quote requires manager approval before confirmation.',
            ]);
        }

        $quote->loadMissing(['lines', 'order']);

        $order = DB::transaction(function () use ($quote, $actor) {
            $order = $this->conversionService->createOrderFromQuote($quote, $actor);

            $quote->update([
                'status' => SalesQuote::STATUS_CONFIRMED,
                'updated_by' => $actor->id,
            ]);

            return $order;
        });

        $this->syncPendingApprovals((string) $quote->company_id, $actor->id);

        return $order->fresh(['partner:id,name', 'quote:id,quote_number', 'lines.product:id,name,sku', 'approvedBy:id,name', 'confirmedBy:id,name']);
    }

    public function delete(SalesQuote $quote, ?string $actorId = null): void
    {
        $companyId = (string) $quote->company_id;
        $quote->lines()->delete();
        $quote->delete();

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
