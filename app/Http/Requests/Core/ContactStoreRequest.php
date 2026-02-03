<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class ContactStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'uuid', 'exists:partners,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['required', 'boolean'],
        ];
    }
}
