<?php

namespace App\Http\Requests\Accounting\Concerns;

trait ValidatesAccountingManualJournalLines
{
    public function accountingManualJournalLineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', $this->companyScopedExists('accounting_accounts')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    public function validateAccountingManualJournalLines($validator): void
    {
        $validator->after(function ($validator): void {
            $lines = $this->input('lines', []);

            if (! is_array($lines) || count($lines) < 2) {
                $validator->errors()->add('lines', 'Manual journals require at least two lines.');

                return;
            }

            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($lines as $index => $line) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit <= 0 && $credit <= 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'Each line must include either a debit or a credit amount.');
                }

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}.credit", 'A journal line cannot contain both debit and credit amounts.');
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (abs(round($totalDebit - $totalCredit, 2)) > 0.01) {
                $validator->errors()->add('lines', 'Manual journal lines must be balanced before posting.');
            }
        });
    }
}
