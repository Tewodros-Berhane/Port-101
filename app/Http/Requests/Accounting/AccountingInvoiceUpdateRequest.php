<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingInvoiceLines;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Accounting\Models\AccountingInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountingInvoiceUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use ValidatesAccountingInvoiceLines;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'document_type' => ['required', 'string', Rule::in(AccountingInvoice::TYPES)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
            ...$this->accountingInvoiceLineRules(),
        ];
    }
}
