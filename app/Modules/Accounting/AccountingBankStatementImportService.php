<?php

namespace App\Modules\Accounting;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingBankStatementImport;
use App\Modules\Accounting\Models\AccountingBankStatementImportLine;
use App\Modules\Accounting\Models\AccountingPayment;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingBankStatementImportService
{
    private const FORMAT_CSV = 'csv';

    private const FORMAT_OFX = 'ofx';

    private const FORMAT_CAMT = 'camt';

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

            $payments = $this->loadEligiblePayments($companyId, $actor);

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
     * @return array<string, array<int, array{id: string, payment_number: string|null, reference: string|null, invoice_number: string|null, partner_name: string|null, payment_date: string|null, amount: float}>>
     */
    public function candidatePaymentsForImport(
        AccountingBankStatementImport $statementImport,
        ?User $actor = null,
    ): array {
        $statementImport->loadMissing(['lines.payment.invoice.partner']);

        $payments = $this->loadEligiblePayments(
            companyId: (string) $statementImport->company_id,
            actor: $actor,
        );

        return $statementImport->lines
            ->mapWithKeys(function (AccountingBankStatementImportLine $line) use ($payments, $statementImport) {
                $reservedPaymentIds = $statementImport->lines
                    ->filter(fn (AccountingBankStatementImportLine $candidate) => $candidate->id !== $line->id)
                    ->filter(fn (AccountingBankStatementImportLine $candidate) => $candidate->match_status === AccountingBankStatementImportLine::MATCH_STATUS_MATCHED)
                    ->filter(fn (AccountingBankStatementImportLine $candidate) => $candidate->payment_id !== null)
                    ->map(fn (AccountingBankStatementImportLine $candidate) => (string) $candidate->payment_id)
                    ->values()
                    ->all();

                $candidates = $this->findCandidatePayments(
                    row: $this->lineToRow($line),
                    payments: $payments,
                    usedPaymentIds: $reservedPaymentIds,
                    currentPaymentId: $line->payment_id ? (string) $line->payment_id : null,
                )
                    ->map(fn (AccountingPayment $payment) => [
                        'id' => (string) $payment->id,
                        'payment_number' => $payment->payment_number,
                        'reference' => $payment->reference,
                        'invoice_number' => $payment->invoice?->invoice_number,
                        'partner_name' => $payment->invoice?->partner?->name,
                        'payment_date' => $payment->payment_date?->toDateString(),
                        'amount' => (float) $payment->amount,
                    ])
                    ->values()
                    ->all();

                return [(string) $line->id => $candidates];
            })
            ->all();
    }

    public function rematchLine(
        AccountingBankStatementImportLine $line,
        ?string $paymentId,
        ?User $actor = null,
    ): AccountingBankStatementImportLine {
        return DB::transaction(function () use ($line, $paymentId, $actor) {
            $line = AccountingBankStatementImportLine::query()
                ->with(['import', 'payment.invoice.partner'])
                ->lockForUpdate()
                ->findOrFail($line->id);

            $statementImport = AccountingBankStatementImport::query()
                ->with('lines:id,bank_statement_import_id,match_status,payment_id')
                ->lockForUpdate()
                ->findOrFail($line->bank_statement_import_id);

            if ($statementImport->reconciled_batch_id) {
                abort(422, 'Cannot change matches on a reconciled bank statement import.');
            }

            if (! $paymentId || trim($paymentId) === '') {
                $line->update([
                    'payment_id' => null,
                    'match_status' => AccountingBankStatementImportLine::MATCH_STATUS_UNMATCHED,
                    'updated_by' => $actor?->id,
                ]);

                return $line->fresh(['payment.invoice.partner']);
            }

            $reservedPaymentIds = $statementImport->lines
                ->filter(fn (AccountingBankStatementImportLine $candidate) => $candidate->id !== $line->id)
                ->filter(fn (AccountingBankStatementImportLine $candidate) => $candidate->match_status === AccountingBankStatementImportLine::MATCH_STATUS_MATCHED)
                ->filter(fn (AccountingBankStatementImportLine $candidate) => $candidate->payment_id !== null)
                ->map(fn (AccountingBankStatementImportLine $candidate) => (string) $candidate->payment_id)
                ->values()
                ->all();

            if (in_array($paymentId, $reservedPaymentIds, true)) {
                abort(422, 'Selected payment is already assigned to another statement line.');
            }

            $payment = $this->loadEligiblePayments(
                companyId: (string) $statementImport->company_id,
                actor: $actor,
            )->firstWhere('id', $paymentId);

            if (! $payment) {
                abort(422, 'Selected payment is not available for statement matching.');
            }

            $line->update([
                'payment_id' => $payment->id,
                'match_status' => AccountingBankStatementImportLine::MATCH_STATUS_MATCHED,
                'updated_by' => $actor?->id,
            ]);

            return $line->fresh(['payment.invoice.partner']);
        });
    }

    /**
     * @return Collection<int, array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}>
     */
    private function parseRows(UploadedFile $file): Collection
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            abort(422, 'Unable to read statement file.');
        }

        return match ($this->detectFormat($contents, $file->getClientOriginalName())) {
            self::FORMAT_OFX => $this->parseOfxRows($contents),
            self::FORMAT_CAMT => $this->parseCamtRows($contents),
            default => $this->parseCsvRows($file),
        };
    }

    /**
     * @return Collection<int, array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}>
     */
    private function parseCsvRows(UploadedFile $file): Collection
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
     * @return Collection<int, array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}>
     */
    private function parseOfxRows(string $contents): Collection
    {
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $contents, $matches);

        $blocks = collect($matches[1] ?? []);

        if ($blocks->isEmpty()) {
            abort(422, 'Unable to parse any OFX statement transactions.');
        }

        return $blocks
            ->map(function (string $block) {
                $fields = [];

                preg_match_all('/<([A-Z0-9_]+)>([^<\r\n]+)/i', $block, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $fields[strtoupper((string) $match[1])] = trim(html_entity_decode((string) $match[2]));
                }

                $amount = $fields['TRNAMT'] ?? null;

                if ($amount === null || trim((string) $amount) === '') {
                    return null;
                }

                return [
                    'transaction_date' => $this->normalizeDate(
                        $fields['DTPOSTED']
                            ?? $fields['DTUSER']
                            ?? null,
                    ),
                    'reference' => $fields['CHECKNUM']
                        ?? $fields['REFNUM']
                        ?? $fields['FITID']
                        ?? $fields['SRVRTID']
                        ?? $fields['NAME']
                        ?? null,
                    'description' => $fields['MEMO']
                        ?? $fields['NAME']
                        ?? $fields['PAYEE']
                        ?? null,
                    'amount' => abs((float) str_replace(',', '', (string) $amount)),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}>
     */
    private function parseCamtRows(string $contents): Collection
    {
        $document = new DOMDocument();
        $previousState = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (! $loaded) {
            abort(422, 'Unable to parse CAMT XML statement.');
        }

        $xpath = new DOMXPath($document);
        $entryNodes = $xpath->query('//*[local-name()="Ntry"]');

        if (! $entryNodes instanceof DOMNodeList || $entryNodes->length === 0) {
            abort(422, 'CAMT XML does not contain any statement entries.');
        }

        $rows = collect();

        foreach ($entryNodes as $entryNode) {
            $entryDate = $this->normalizeDate(
                $this->firstXPathValue($xpath, $entryNode, './*[local-name()="BookgDt"]/*[local-name()="Dt"]')
                    ?? $this->firstXPathValue($xpath, $entryNode, './*[local-name()="BookgDt"]/*[local-name()="DtTm"]')
                    ?? $this->firstXPathValue($xpath, $entryNode, './*[local-name()="ValDt"]/*[local-name()="Dt"]')
                    ?? $this->firstXPathValue($xpath, $entryNode, './*[local-name()="ValDt"]/*[local-name()="DtTm"]'),
            );

            $entryReference = $this->firstNonEmpty([
                $this->firstXPathValue($xpath, $entryNode, './*[local-name()="NtryRef"]'),
                $this->firstXPathValue($xpath, $entryNode, './/*[local-name()="AcctSvcrRef"]'),
            ]);

            $entryDescription = $this->firstNonEmpty([
                $this->firstXPathValue($xpath, $entryNode, './*[local-name()="AddtlNtryInf"]'),
                $this->firstXPathValue($xpath, $entryNode, './/*[local-name()="Ustrd"]'),
            ]);

            $entryAmount = $this->firstXPathValue($xpath, $entryNode, './*[local-name()="Amt"]');
            $entryIndicator = $this->firstXPathValue($xpath, $entryNode, './*[local-name()="CdtDbtInd"]');
            $transactionNodes = $xpath->query('.//*[local-name()="TxDtls"]', $entryNode);

            if ($transactionNodes instanceof DOMNodeList && $transactionNodes->length > 0) {
                foreach ($transactionNodes as $transactionNode) {
                    $amount = $this->normalizeAmountValue(
                        $this->firstNonEmpty([
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="TxAmt"]/*[local-name()="Amt"]'),
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="AmtDtls"]//*[local-name()="Amt"]'),
                            $entryAmount,
                        ]),
                        $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="CdtDbtInd"]')
                            ?? $entryIndicator,
                    );

                    if ($amount === null) {
                        continue;
                    }

                    $rows->push([
                        'transaction_date' => $this->normalizeDate(
                            $this->firstNonEmpty([
                                $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="BookgDt"]/*[local-name()="Dt"]'),
                                $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="BookgDt"]/*[local-name()="DtTm"]'),
                                $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="ValDt"]/*[local-name()="Dt"]'),
                                $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="ValDt"]/*[local-name()="DtTm"]'),
                                $entryDate,
                            ]),
                        ),
                        'reference' => $this->firstNonEmpty([
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="EndToEndId"]'),
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="InstrId"]'),
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="TxId"]'),
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="AcctSvcrRef"]'),
                            $entryReference,
                        ]),
                        'description' => $this->firstNonEmpty([
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="Ustrd"]'),
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="AddtlTxInf"]'),
                            $this->firstXPathValue($xpath, $transactionNode, './/*[local-name()="Nm"]'),
                            $entryDescription,
                        ]),
                        'amount' => $amount,
                    ]);
                }

                continue;
            }

            $amount = $this->normalizeAmountValue($entryAmount, $entryIndicator);

            if ($amount === null) {
                continue;
            }

            $rows->push([
                'transaction_date' => $entryDate,
                'reference' => $entryReference,
                'description' => $entryDescription,
                'amount' => $amount,
            ]);
        }

        return $rows->values();
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
     * @param  array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}  $row
     * @param  array<int, string>  $usedPaymentIds
     * @return Collection<int, AccountingPayment>
     */
    private function findCandidatePayments(
        array $row,
        Collection $payments,
        array $usedPaymentIds = [],
        ?string $currentPaymentId = null,
    ): Collection {
        $referenceToken = $this->normalizeReference(
            $row['reference'] ?: $row['description'],
        );
        $descriptionToken = $this->normalizeReference($row['description']);
        $amount = round((float) $row['amount'], 2);
        $transactionDate = $row['transaction_date']
            ? CarbonImmutable::parse($row['transaction_date'])
            : null;

        return $payments
            ->reject(function (AccountingPayment $payment) use ($usedPaymentIds, $currentPaymentId) {
                return in_array((string) $payment->id, $usedPaymentIds, true)
                    && (string) $payment->id !== $currentPaymentId;
            })
            ->map(function (AccountingPayment $payment) use (
                $amount,
                $referenceToken,
                $descriptionToken,
                $transactionDate,
                $currentPaymentId,
            ) {
                if (abs(round((float) $payment->amount, 2) - $amount) > 0.01) {
                    return null;
                }

                $tokens = collect([
                    $payment->reference,
                    $payment->payment_number,
                    $payment->invoice?->invoice_number,
                    $payment->invoice?->partner?->name,
                ])
                    ->filter()
                    ->map(fn ($value) => $this->normalizeReference((string) $value))
                    ->filter()
                    ->values();

                $score = 0;

                if ($referenceToken && $tokens->contains($referenceToken)) {
                    $score += 120;
                }

                if ($descriptionToken && $tokens->contains($descriptionToken)) {
                    $score += 45;
                }

                if ($transactionDate && $payment->payment_date) {
                    $dayDifference = abs($payment->payment_date->diffInDays($transactionDate));

                    if ($dayDifference <= 3) {
                        $score += 35;
                    } elseif ($dayDifference <= 7) {
                        $score += 20;
                    } elseif ($dayDifference <= 30) {
                        $score += 10;
                    }
                } else {
                    $score += 5;
                }

                if ((string) $payment->id === $currentPaymentId) {
                    $score += 200;
                }

                if ($score === 0) {
                    $score = 1;
                }

                return [
                    'score' => $score,
                    'payment' => $payment,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->take(6)
            ->map(fn (array $candidate) => $candidate['payment'])
            ->values();
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
        $value = ltrim($value, "\xEF\xBB\xBF");

        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value) ?: ''));
    }

    private function normalizeDate(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $normalizedValue = preg_replace('/\[[^\]]+\]/', '', trim($value)) ?: trim($value);

        if (preg_match('/^(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})/', $normalizedValue, $matches) === 1) {
            return sprintf(
                '%s-%s-%s',
                $matches['year'],
                $matches['month'],
                $matches['day'],
            );
        }

        try {
            return CarbonImmutable::parse($normalizedValue)->toDateString();
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

    private function detectFormat(string $contents, string $fileName): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $trimmedContents = ltrim($contents, "\xEF\xBB\xBF\t\n\r ");
        $upperContents = strtoupper($trimmedContents);

        if ($extension === 'ofx' || str_contains($upperContents, 'OFXHEADER:') || str_contains($upperContents, '<OFX>')) {
            return self::FORMAT_OFX;
        }

        if (
            $extension === 'xml'
            && (
                str_contains($trimmedContents, 'BkToCstmrStmt')
                || str_contains($trimmedContents, 'BkToCstmrDbtCdtNtfctn')
                || str_contains($trimmedContents, 'urn:iso:std:iso:20022:tech:xsd:camt.')
            )
        ) {
            return self::FORMAT_CAMT;
        }

        if (
            str_contains($trimmedContents, 'BkToCstmrStmt')
            || str_contains($trimmedContents, 'BkToCstmrDbtCdtNtfctn')
            || str_contains($trimmedContents, 'urn:iso:std:iso:20022:tech:xsd:camt.')
        ) {
            return self::FORMAT_CAMT;
        }

        return self::FORMAT_CSV;
    }

    private function firstXPathValue(DOMXPath $xpath, DOMNode $contextNode, string $query): ?string
    {
        $nodes = $xpath->query($query, $contextNode);

        if (! $nodes instanceof DOMNodeList || $nodes->length === 0) {
            return null;
        }

        foreach ($nodes as $node) {
            $value = trim((string) $node->textContent);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function normalizeAmountValue(?string $amount, ?string $creditDebitIndicator = null): ?float
    {
        if ($amount === null || trim($amount) === '') {
            return null;
        }

        $normalizedAmount = (float) str_replace(',', '', trim($amount));

        if ($creditDebitIndicator && strtoupper(trim($creditDebitIndicator)) === 'DBIT') {
            $normalizedAmount *= -1;
        }

        return abs($normalizedAmount);
    }

    private function loadEligiblePayments(string $companyId, ?User $actor = null): Collection
    {
        return AccountingPayment::query()
            ->with(['invoice:id,invoice_number,partner_id', 'invoice.partner:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('status', [
                AccountingPayment::STATUS_POSTED,
                AccountingPayment::STATUS_RECONCILED,
            ])
            ->whereNull('bank_reconciled_at')
            ->when($actor, fn ($builder) => $actor->applyDataScopeToQuery($builder))
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array{transaction_date: string|null, reference: string|null, description: string|null, amount: float}
     */
    private function lineToRow(AccountingBankStatementImportLine $line): array
    {
        return [
            'transaction_date' => $line->transaction_date?->toDateString(),
            'reference' => $line->reference,
            'description' => $line->description,
            'amount' => (float) $line->amount,
        ];
    }
}
