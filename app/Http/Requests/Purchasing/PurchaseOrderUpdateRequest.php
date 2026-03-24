<?php

namespace App\Http\Requests\Purchasing;

use App\Core\MasterData\Models\Partner;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExternalReferenceRule;
use App\Http\Requests\Purchasing\Concerns\ValidatesPurchasingLines;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use CompanyScopedExternalReferenceRule;
    use ValidatesPurchasingLines;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orderId = (string) $this->route('order')?->id;

        return [
            'external_reference' => $this->externalReferenceRules('purchase_orders', $orderId),
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

            if (! $partner) {
                return;
            }

            if (! in_array($partner->type, ['vendor', 'both'], true)) {
                $validator->errors()->add(
                    'partner_id',
                    'Selected partner must be a vendor or both-type partner.',
                );
            }

            if (! $partner->is_active) {
                $validator->errors()->add(
                    'partner_id',
                    'Selected vendor is inactive.',
                );
            }
        });
    }
}
