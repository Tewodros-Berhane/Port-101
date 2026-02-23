<?php

namespace App\Http\Requests\Purchasing;

use App\Core\MasterData\Models\Partner;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Purchasing\Concerns\ValidatesPurchasingLines;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseRfqUpdateRequest extends FormRequest
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
            'partner_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'rfq_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:rfq_date'],
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
