<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReplenishmentConvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'partner_id' => [
                'nullable',
                'string',
                Rule::exists('partners', 'id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereIn('type', ['vendor', 'both'])
                        ->where('is_active', true)),
            ],
        ];
    }
}
