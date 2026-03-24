<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExternalReferenceRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountingPaymentStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use CompanyScopedExternalReferenceRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_reference' => $this->externalReferenceRules('accounting_payments'),
            'invoice_id' => ['required', 'uuid', $this->companyScopedExists('accounting_invoices')],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'method' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
