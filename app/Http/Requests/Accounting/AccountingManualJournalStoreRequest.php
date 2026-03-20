<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingManualJournalLines;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Accounting\Models\AccountingJournal;
use Illuminate\Foundation\Http\FormRequest;

class AccountingManualJournalStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;
    use ValidatesAccountingManualJournalLines;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journal_id' => ['required', 'uuid', $this->companyScopedExists('accounting_journals')],
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:128'],
            'description' => ['required', 'string', 'max:255'],
            ...$this->accountingManualJournalLineRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateAccountingManualJournalLines($validator);

        $validator->after(function ($validator): void {
            $journalId = $this->input('journal_id');

            if (! $journalId) {
                return;
            }

            $journal = AccountingJournal::query()->find($journalId);

            if (! $journal) {
                return;
            }

            if ($journal->journal_type !== AccountingJournal::TYPE_GENERAL) {
                $validator->errors()->add('journal_id', 'Manual journal entries must use a general journal.');
            }

            if (! $journal->is_active) {
                $validator->errors()->add('journal_id', 'Selected journal is inactive.');
            }
        });
    }
}
