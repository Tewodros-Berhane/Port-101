<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddressUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'type' => ['required', 'string', Rule::in(['billing', 'shipping', 'other'])],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'is_primary' => ['required', 'boolean'],
        ];
    }
}
