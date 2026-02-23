<?php

namespace App\Modules\Purchasing;

use App\Modules\Purchasing\Models\PurchaseRfq;
use Illuminate\Support\Facades\DB;

class PurchasingRfqWorkflowService
{
    public function __construct(
        private readonly PurchasingTotalsService $totalsService,
        private readonly PurchasingNumberingService $numberingService,
    ) {}

    /**
     * @param  array{
     *     partner_id: string,
     *     rfq_date?: string|null,
     *     valid_until?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $attributes
     */
    public function createDraft(
        array $attributes,
        string $companyId,
        ?string $actorId = null
    ): PurchaseRfq {
        $calculated = $this->totalsService->calculate($attributes['lines'] ?? []);
        $totals = $calculated['totals'];

        return DB::transaction(function () use (
            $attributes,
            $calculated,
            $totals,
            $companyId,
            $actorId
        ) {
            $rfq = PurchaseRfq::create([
                'company_id' => $companyId,
                'partner_id' => $attributes['partner_id'],
                'rfq_number' => $this->numberingService->nextRfqNumber($companyId, $actorId),
                'status' => PurchaseRfq::STATUS_DRAFT,
                'rfq_date' => $attributes['rfq_date'] ?? now()->toDateString(),
                'valid_until' => $attributes['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $rfq->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ])
                    ->all()
            );

            return $rfq->fresh(['lines']);
        });
    }

    /**
     * @param  array{
     *     partner_id: string,
     *     rfq_date?: string|null,
     *     valid_until?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $attributes
     */
    public function updateDraft(
        PurchaseRfq $rfq,
        array $attributes,
        ?string $actorId = null
    ): PurchaseRfq {
        return DB::transaction(function () use ($rfq, $attributes, $actorId) {
            $rfq = PurchaseRfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if ($rfq->status !== PurchaseRfq::STATUS_DRAFT) {
                abort(422, 'Only draft RFQs can be updated.');
            }

            $calculated = $this->totalsService->calculate($attributes['lines'] ?? []);
            $totals = $calculated['totals'];

            $rfq->update([
                'partner_id' => $attributes['partner_id'],
                'rfq_date' => $attributes['rfq_date'] ?? $rfq->rfq_date?->toDateString(),
                'valid_until' => $attributes['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'notes' => $attributes['notes'] ?? null,
                'updated_by' => $actorId,
            ]);

            $rfq->lines()->delete();
            $rfq->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => (string) $rfq->company_id,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ])
                    ->all()
            );

            return $rfq->fresh(['lines']);
        });
    }

    public function send(PurchaseRfq $rfq, ?string $actorId = null): PurchaseRfq
    {
        return DB::transaction(function () use ($rfq, $actorId) {
            $rfq = PurchaseRfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if ($rfq->status === PurchaseRfq::STATUS_SENT) {
                return $rfq;
            }

            if ($rfq->status !== PurchaseRfq::STATUS_DRAFT) {
                abort(422, 'Only draft RFQs can be sent.');
            }

            $rfq->update([
                'status' => PurchaseRfq::STATUS_SENT,
                'sent_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $rfq->fresh();
        });
    }

    public function markVendorResponded(PurchaseRfq $rfq, ?string $actorId = null): PurchaseRfq
    {
        return DB::transaction(function () use ($rfq, $actorId) {
            $rfq = PurchaseRfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if ($rfq->status === PurchaseRfq::STATUS_VENDOR_RESPONDED) {
                return $rfq;
            }

            if (! in_array($rfq->status, [
                PurchaseRfq::STATUS_DRAFT,
                PurchaseRfq::STATUS_SENT,
            ], true)) {
                abort(422, 'Only draft or sent RFQs can be marked as responded.');
            }

            $rfq->update([
                'status' => PurchaseRfq::STATUS_VENDOR_RESPONDED,
                'vendor_responded_at' => now(),
                'sent_at' => $rfq->sent_at ?? now(),
                'updated_by' => $actorId,
            ]);

            return $rfq->fresh();
        });
    }

    public function select(PurchaseRfq $rfq, ?string $actorId = null): PurchaseRfq
    {
        return DB::transaction(function () use ($rfq, $actorId) {
            $rfq = PurchaseRfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if ($rfq->status === PurchaseRfq::STATUS_SELECTED) {
                return $rfq;
            }

            if (! in_array($rfq->status, [
                PurchaseRfq::STATUS_SENT,
                PurchaseRfq::STATUS_VENDOR_RESPONDED,
            ], true)) {
                abort(422, 'RFQ must be sent or vendor responded before selection.');
            }

            $rfq->update([
                'status' => PurchaseRfq::STATUS_SELECTED,
                'selected_at' => now(),
                'vendor_responded_at' => $rfq->vendor_responded_at ?? now(),
                'updated_by' => $actorId,
            ]);

            return $rfq->fresh();
        });
    }
}
