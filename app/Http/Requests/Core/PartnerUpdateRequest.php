<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\Core\Concerns\CompanyScopedExternalReferenceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartnerUpdateRequest extends FormRequest
{
    use CompanyScopedExternalReferenceRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;
        $partnerId = $this->route('partner')?->id;

        return [
            'external_reference' => $this->externalReferenceRules('partners', $partnerId),
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('partners', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($partnerId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['customer', 'vendor', 'both'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
