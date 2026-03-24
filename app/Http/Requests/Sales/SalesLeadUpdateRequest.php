<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExternalReferenceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesLeadUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use CompanyScopedExternalReferenceRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $leadId = (string) $this->route('lead')?->id;

        return [
            'external_reference' => $this->externalReferenceRules('sales_leads', $leadId),
            'partner_id' => ['nullable', 'uuid', $this->companyScopedExists('partners')],
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'string', Rule::in(['new', 'qualified', 'quoted', 'won', 'lost'])],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
