<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Accounting\Models\AccountingManualJournalLine;
use Illuminate\Support\Facades\DB;

class AccountingManualJournalWorkflowService
{
    public function __construct(
        private readonly AccountingLedgerPostingService $ledgerPostingService,
        private readonly AccountingNumberingService $numberingService,
        private readonly AccountingPeriodGuardService $periodGuardService,
        private readonly AccountingManualJournalApprovalPolicyService $approvalPolicyService,
    ) {}

    /**
     * @param  array{
     *     journal_id: string,
     *     entry_date: string,
     *     reference?: string|null,
     *     description: string,
     *     lines: array<int, array{account_id: string, description?: string|null, debit?: int|float|string|null, credit?: int|float|string|null}>
     * }  $attributes
     */
    public function createDraft(array $attributes, string $companyId, ?string $actorId = null): AccountingManualJournal
    {
        return DB::transaction(function () use ($attributes, $companyId, $actorId) {
            $journal = $this->resolveJournal((string) $attributes['journal_id'], $companyId);
            $this->assertBalancedLines($attributes['lines']);
            $totalAmount = $this->journalTotal($attributes['lines']);
            $requiresApproval = $this->approvalPolicyService->requiresApproval($companyId, $totalAmount);

            $manualJournal = AccountingManualJournal::create([
                'company_id' => $companyId,
                'journal_id' => $journal->id,
                'entry_number' => $this->numberingService->nextManualJournalNumber(
                    companyId: $companyId,
                    actorId: $actorId,
                ),
                'status' => AccountingManualJournal::STATUS_DRAFT,
                'requires_approval' => $requiresApproval,
                'approval_status' => $requiresApproval
                    ? AccountingManualJournal::APPROVAL_STATUS_PENDING
                    : AccountingManualJournal::APPROVAL_STATUS_NOT_REQUIRED,
                'approval_requested_at' => $requiresApproval ? now() : null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'entry_date' => $attributes['entry_date'],
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->replaceLines($manualJournal, $attributes['lines'], $actorId);

            return $manualJournal->fresh(['journal', 'lines.account']);
        });
    }

    /**
     * @param  array{
     *     journal_id: string,
     *     entry_date: string,
     *     reference?: string|null,
     *     description: string,
     *     lines: array<int, array{account_id: string, description?: string|null, debit?: int|float|string|null, credit?: int|float|string|null}>
     * }  $attributes
     */
    public function updateDraft(
        AccountingManualJournal $manualJournal,
        array $attributes,
        ?string $actorId = null,
    ): AccountingManualJournal {
        return DB::transaction(function () use ($manualJournal, $attributes, $actorId) {
            $manualJournal = AccountingManualJournal::query()
                ->lockForUpdate()
                ->findOrFail($manualJournal->id);

            if ($manualJournal->status !== AccountingManualJournal::STATUS_DRAFT) {
                abort(422, 'Only draft manual journals can be updated.');
            }

            $journal = $this->resolveJournal((string) $attributes['journal_id'], (string) $manualJournal->company_id);
            $this->assertBalancedLines($attributes['lines']);
            $totalAmount = $this->journalTotal($attributes['lines']);
            $requiresApproval = $this->approvalPolicyService->requiresApproval(
                (string) $manualJournal->company_id,
                $totalAmount,
            );

            $manualJournal->update([
                'journal_id' => $journal->id,
                'requires_approval' => $requiresApproval,
                'approval_status' => $requiresApproval
                    ? AccountingManualJournal::APPROVAL_STATUS_PENDING
                    : AccountingManualJournal::APPROVAL_STATUS_NOT_REQUIRED,
                'approval_requested_at' => $requiresApproval ? now() : null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'entry_date' => $attributes['entry_date'],
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'],
                'updated_by' => $actorId,
            ]);

            $this->replaceLines($manualJournal, $attributes['lines'], $actorId);

            return $manualJournal->fresh(['journal', 'lines.account']);
        });
    }

    public function post(AccountingManualJournal $manualJournal, ?string $actorId = null): AccountingManualJournal
    {
        return DB::transaction(function () use ($manualJournal, $actorId) {
            $manualJournal = AccountingManualJournal::query()
                ->with(['journal', 'lines.account'])
                ->lockForUpdate()
                ->findOrFail($manualJournal->id);

            if ($manualJournal->status === AccountingManualJournal::STATUS_POSTED) {
                return $manualJournal;
            }

            if ($manualJournal->status === AccountingManualJournal::STATUS_REVERSED) {
                abort(422, 'Reversed manual journals cannot be posted.');
            }

            if (
                $manualJournal->requires_approval
                && $manualJournal->approval_status !== AccountingManualJournal::APPROVAL_STATUS_APPROVED
            ) {
                abort(422, 'Manual journal must be approved before posting.');
            }

            $this->assertBalancedLines(
                $manualJournal->lines
                    ->map(fn (AccountingManualJournalLine $line) => [
                        'account_id' => (string) $line->account_id,
                        'description' => $line->description,
                        'debit' => (float) $line->debit,
                        'credit' => (float) $line->credit,
                    ])
                    ->all(),
            );

            $this->periodGuardService->assertPostingAllowed(
                companyId: (string) $manualJournal->company_id,
                postingDate: $manualJournal->entry_date?->toDateString() ?? now()->toDateString(),
            );

            $manualJournal->update([
                'status' => AccountingManualJournal::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->ledgerPostingService->postManualJournal($manualJournal, $actorId);

            return $manualJournal->fresh(['journal', 'lines.account']);
        });
    }

    public function reverse(
        AccountingManualJournal $manualJournal,
        string $reason,
        ?string $actorId = null,
    ): AccountingManualJournal {
        return DB::transaction(function () use ($manualJournal, $reason, $actorId) {
            $manualJournal = AccountingManualJournal::query()
                ->with(['journal', 'lines.account'])
                ->lockForUpdate()
                ->findOrFail($manualJournal->id);

            if ($manualJournal->status === AccountingManualJournal::STATUS_REVERSED) {
                return $manualJournal;
            }

            if ($manualJournal->status !== AccountingManualJournal::STATUS_POSTED) {
                abort(422, 'Only posted manual journals can be reversed.');
            }

            $this->periodGuardService->assertPostingAllowed(
                companyId: (string) $manualJournal->company_id,
                postingDate: now()->toDateString(),
            );

            $this->ledgerPostingService->reverseManualJournal(
                manualJournal: $manualJournal,
                reason: $reason,
                actorId: $actorId,
            );

            $manualJournal->update([
                'status' => AccountingManualJournal::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by' => $actorId,
                'reversal_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            return $manualJournal->fresh(['journal', 'lines.account']);
        });
    }

    /**
     * @param  array<int, array{account_id: string, description?: string|null, debit?: int|float|string|null, credit?: int|float|string|null}>  $lines
     */
    private function replaceLines(AccountingManualJournal $manualJournal, array $lines, ?string $actorId = null): void
    {
        $manualJournal->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            AccountingManualJournalLine::create([
                'company_id' => (string) $manualJournal->company_id,
                'manual_journal_id' => (string) $manualJournal->id,
                'account_id' => (string) $line['account_id'],
                'line_order' => $index + 1,
                'description' => $line['description'] ?? null,
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
    }

    private function resolveJournal(string $journalId, string $companyId): AccountingJournal
    {
        $journal = AccountingJournal::query()
            ->where('company_id', $companyId)
            ->findOrFail($journalId);

        if ($journal->journal_type !== AccountingJournal::TYPE_GENERAL) {
            abort(422, 'Manual journal entries must use a general journal.');
        }

        if (! $journal->is_active) {
            abort(422, 'Selected journal is inactive.');
        }

        return $journal;
    }

    /**
     * @param  array<int, array{account_id: string, description?: string|null, debit?: int|float|string|null, credit?: int|float|string|null}>  $lines
     */
    private function assertBalancedLines(array $lines): void
    {
        if (count($lines) < 2) {
            abort(422, 'Manual journals require at least two lines.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit <= 0 && $credit <= 0) {
                abort(422, 'Each manual journal line must include either a debit or a credit amount.');
            }

            if ($debit > 0 && $credit > 0) {
                abort(422, 'A manual journal line cannot contain both debit and credit amounts.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (abs(round($totalDebit - $totalCredit, 2)) > 0.01) {
            abort(422, 'Manual journal lines must be balanced before posting.');
        }
    }

    /**
     * @param  array<int, array{account_id: string, description?: string|null, debit?: int|float|string|null, credit?: int|float|string|null}>  $lines
     */
    private function journalTotal(array $lines): float
    {
        return round((float) collect($lines)->sum(
            fn (array $line) => round((float) ($line['debit'] ?? 0), 2),
        ), 2);
    }
}
