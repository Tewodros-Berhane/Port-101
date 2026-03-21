<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Accounting\Models\AccountingJournal;
use Illuminate\Foundation\Http\FormRequest;

class AccountingBankStatementImportRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journal_id' => ['required', 'uuid', $this->companyScopedExists('accounting_journals')],
            'statement_reference' => ['required', 'string', 'max:128'],
            'statement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => [
                'required',
                'file',
                'max:4096',
                function (string $attribute, $value, \Closure $fail): void {
                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if (! in_array($extension, ['csv', 'txt', 'ofx', 'xml'], true)) {
                        $fail('Statement imports support CSV, TXT, OFX, and XML files only.');
                    }
                },
            ],
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
                $validator->errors()->add('journal_id', 'Bank statement imports must use a bank journal.');
            }

            if (! $journal->is_active) {
                $validator->errors()->add('journal_id', 'Selected journal is inactive.');
            }
        });
    }
}
