<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->input('role');
        $requiresCompany = in_array($role, ['company_owner', 'company_member'], true);

        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => [
                'required',
                'string',
                Rule::in(['platform_admin', 'company_owner', 'company_member']),
            ],
            'company_id' => [
                Rule::requiredIf($requiresCompany),
                'nullable',
                'uuid',
                'exists:companies,id',
            ],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
