<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceListStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('price_lists', 'name')->where('company_id', $companyId),
            ],
            'currency_id' => ['nullable', 'uuid', $this->companyScopedExists('currencies')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
