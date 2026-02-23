<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesLeadStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['nullable', 'uuid', $this->companyScopedExists('partners')],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'string', Rule::in(['new', 'qualified', 'quoted', 'won', 'lost'])],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
