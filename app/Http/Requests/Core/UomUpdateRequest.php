<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UomUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;
        $uomId = $this->route('uom')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('uoms', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($uomId),
            ],
            'symbol' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
