<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Accounting\Models\AccountingJournal;
use Illuminate\Foundation\Http\FormRequest;

class AccountingBankReconciliationStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journal_id' => ['nullable', 'uuid', 'required_without:bank_statement_import_id', $this->companyScopedExists('accounting_journals')],
            'statement_reference' => ['nullable', 'string', 'max:128', 'required_without:bank_statement_import_id'],
            'statement_date' => ['nullable', 'date', 'required_without:bank_statement_import_id'],
            'notes' => ['nullable', 'string'],
            'payment_ids' => ['nullable', 'array', 'min:1', 'required_without:bank_statement_import_id'],
            'payment_ids.*' => ['required', 'uuid', 'distinct', $this->companyScopedExists('accounting_payments')],
            'bank_statement_import_id' => ['nullable', 'uuid', 'required_without:payment_ids', $this->companyScopedExists('accounting_bank_statement_imports')],
            'line_ids' => ['nullable', 'array', 'min:1', 'required_with:bank_statement_import_id'],
            'line_ids.*' => ['required', 'uuid', 'distinct', $this->companyScopedExists('accounting_bank_statement_import_lines')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $journalId = $this->input('journal_id');

            if (! $journalId) {
                return;
            }

            $journal = AccountingJournal::query()->find($journalId);

            if (! $journal) {
                return;
            }

            if ($journal->journal_type !== AccountingJournal::TYPE_BANK) {
                $validator->errors()->add('journal_id', 'Bank reconciliation batches must use a bank journal.');
            }

            if (! $journal->is_active) {
                $validator->errors()->add('journal_id', 'Selected journal is inactive.');
            }
        });
    }
}
