<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExternalReferenceRule;
use App\Http\Requests\Sales\Concerns\ValidatesSalesLines;
use Illuminate\Foundation\Http\FormRequest;

class SalesQuoteUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use CompanyScopedExternalReferenceRule;
    use ValidatesSalesLines;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $quoteId = (string) $this->route('quote')?->id;

        return [
            'external_reference' => $this->externalReferenceRules('sales_quotes', $quoteId),
            'lead_id' => ['nullable', 'uuid', $this->companyScopedExists('sales_leads')],
            'partner_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            ...$this->salesLineRules(),
        ];
    }
}
