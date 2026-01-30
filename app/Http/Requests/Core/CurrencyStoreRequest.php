<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;

        return [
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
