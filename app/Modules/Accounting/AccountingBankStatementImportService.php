<?php

namespace App\Modules\Accounting;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingBankStatementImport;
use App\Modules\Accounting\Models\AccountingBankStatementImportLine;
use App\Modules\Accounting\Models\AccountingPayment;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingBankStatementImportService
{
    /**
     * @param  array{
     *     journal_id: string,
     *     statement_reference: string,
     *     statement_date: string,
     *     notes?: string|null
     * }  $attributes
     */
    public function import(
        UploadedFile $file,
        array $attributes,
        string $companyId,
        ?User $actor = null,
    ): AccountingBankStatementImport {
        return DB::transaction(function () use ($file, $attributes, $companyId, $actor) {
            $rows = $this->parseRows($file);

            if ($rows->isEmpty()) {
                abort(422, 'Statement file does not contain any usable rows.');
            }

            $payments = AccountingPayment::query()
                ->with(['invoice:id,invoice_number,partner_id', 'invoice.partner:id,name'])
                ->where('company_id', $companyId)
                ->whereIn('status', [
                    AccountingPayment::STATUS_POSTED,
                    AccountingPayment::STATUS_RECONCILED,
                ])
                ->whereNull('bank_reconciled_at')
                ->when($actor, fn ($builder) => $actor->applyDataScopeToQuery($builder))
                ->get();

            $statementImport = AccountingBankStatementImport::create([
                'company_id' => $companyId,
                'journal_id' => $attributes['journal_id'],
                'statement_reference' => $attributes['statement_reference'],
                'statement_date' => $attributes['statement_date'],
                'source_file_name' => $file->getClientOriginalName(),
                'notes' => $attributes['notes'] ?? null,
                'imported_by' => $actor?->id,
                'imported_at' => now(),
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $usedPaymentIds = [];

            foreach ($rows as $index => $row) {
                [$matchStatus, $payment] = $this->matchPayment(
                    row: $row,
                    payments: $payments,
                    usedPaymentIds: $usedPaymentIds,
                );

                if ($payment) {
                    $usedPaymentIds[] = (string) $payment->id;
                }

                AccountingBankStatementImportLine::create([
                    'company_id' => $companyId,
                    'bank_statement_import_id' => $statementImport->id,
                    'line_number' => $index + 1,
                    'transaction_date' => $row['transaction_date'],
                    'reference' => $row['reference'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'match_status' => $matchStatus,
                    'payment_id' => $payment?->id,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);
            }

            return $statementImport->fresh([
                'journal',
                'lines.payment.invoice.partner',
            ]);
        });
    }

    /**
     * @return Collection<int, array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}>
     */
    private function parseRows(UploadedFile $file): Collection
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            abort(422, 'Unable to open statement file.');
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            abort(422, 'Statement file is missing a header row.');
        }

        $columns = collect($header)
            ->map(fn ($value) => $this->normalizeHeader((string) $value))
            ->values();

        $rows = collect();

        while (($data = fgetcsv($handle)) !== false) {
            $row = $columns
                ->mapWithKeys(fn (string $column, int $index) => [
                    $column => isset($data[$index]) ? trim((string) $data[$index]) : null,
                ])
                ->all();

            $amount = $this->resolveAmount($row);

            if ($amount === null) {
                continue;
            }

            $rows->push([
                'transaction_date' => $this->normalizeDate(
                    $row['date']
                        ?? $row['transaction_date']
                        ?? $row['posted_date']
                        ?? null
                ),
                'reference' => $row['reference']
                    ?? $row['payment_reference']
                    ?? $row['reference_number']
                    ?? null,
                'description' => $row['description']
                    ?? $row['memo']
                    ?? $row['details']
                    ?? null,
                'amount' => $amount,
            ]);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}  $row
     * @param  array<int, string>  $usedPaymentIds
     * @return array{0: string, 1: AccountingPayment|null}
     */
    private function matchPayment(array $row, Collection $payments, array $usedPaymentIds): array
    {
        $referenceToken = $this->normalizeReference(
            $row['reference'] ?: $row['description'],
        );

        $amount = round((float) $row['amount'], 2);
        $transactionDate = $row['transaction_date']
            ? CarbonImmutable::parse($row['transaction_date'])
            : null;

        $referenceMatches = $payments
            ->filter(function (AccountingPayment $payment) use ($referenceToken, $amount) {
                if (! $referenceToken) {
                    return false;
                }

                $tokens = collect([
                    $payment->reference,
                    $payment->payment_number,
                    $payment->invoice?->invoice_number,
                ])
                    ->filter()
                    ->map(fn ($value) => $this->normalizeReference((string) $value))
                    ->all();

                return in_array($referenceToken, $tokens, true)
                    && abs(round((float) $payment->amount, 2) - $amount) <= 0.01;
            })
            ->values();

        if ($referenceMatches->count() === 1) {
            $payment = $referenceMatches->first();

            if (in_array((string) $payment->id, $usedPaymentIds, true)) {
                return [AccountingBankStatementImportLine::MATCH_STATUS_DUPLICATE, null];
            }

            return [AccountingBankStatementImportLine::MATCH_STATUS_MATCHED, $payment];
        }

        $amountMatches = $payments
            ->filter(function (AccountingPayment $payment) use ($amount, $transactionDate) {
                if (abs(round((float) $payment->amount, 2) - $amount) > 0.01) {
                    return false;
                }

                if (! $transactionDate || ! $payment->payment_date) {
                    return true;
                }

                return abs($payment->payment_date->diffInDays($transactionDate)) <= 7;
            })
            ->values();

        if ($amountMatches->count() === 1) {
            $payment = $amountMatches->first();

            if (in_array((string) $payment->id, $usedPaymentIds, true)) {
                return [AccountingBankStatementImportLine::MATCH_STATUS_DUPLICATE, null];
            }

            return [AccountingBankStatementImportLine::MATCH_STATUS_MATCHED, $payment];
        }

        return [AccountingBankStatementImportLine::MATCH_STATUS_UNMATCHED, null];
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function resolveAmount(array $row): ?float
    {
        $amountValue = $row['amount'] ?? null;

        if ($amountValue !== null && trim($amountValue) !== '') {
            return abs((float) str_replace(',', '', $amountValue));
        }

        foreach (['debit', 'credit'] as $column) {
            $value = $row[$column] ?? null;

            if ($value !== null && trim($value) !== '') {
                return abs((float) str_replace(',', '', $value));
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value) ?: ''));
    }

    private function normalizeDate(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeReference(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $value) ?: '');

        return $normalized !== '' ? $normalized : null;
    }
}
