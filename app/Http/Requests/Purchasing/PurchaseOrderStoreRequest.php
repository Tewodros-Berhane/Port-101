<?php

namespace App\Http\Requests\Purchasing;

use App\Core\MasterData\Models\Partner;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Purchasing\Concerns\ValidatesPurchasingLines;
use App\Modules\Purchasing\Models\PurchaseRfq;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use ValidatesPurchasingLines;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rfq_id' => ['nullable', 'uuid', $this->companyScopedExists('purchase_rfqs')],
            'partner_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'order_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            ...$this->purchasingLineRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $partner = Partner::query()->find($this->input('partner_id'));

            if ($partner && ! in_array($partner->type, ['vendor', 'both'], true)) {
                $validator->errors()->add(
                    'partner_id',
                    'Selected partner must be a vendor or both-type partner.',
                );
            }

            if ($partner && ! $partner->is_active) {
                $validator->errors()->add(
                    'partner_id',
                    'Selected vendor is inactive.',
                );
            }

            if (! $this->filled('rfq_id')) {
                return;
            }

            $rfq = PurchaseRfq::query()->find($this->input('rfq_id'));

            if (! $rfq) {
                return;
            }

            if ((string) $rfq->partner_id !== (string) $this->input('partner_id')) {
                $validator->errors()->add(
                    'partner_id',
                    'PO partner must match the linked RFQ vendor.',
                );
            }
        });
    }
}
