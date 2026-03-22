<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectRecurringBillingRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectRecurringBillingService
{
    public function __construct(
        private readonly ProjectBillableApprovalPolicyService $approvalPolicyService,
        private readonly ProjectInvoiceDraftService $invoiceDraftService,
        private readonly ProjectNotificationService $notificationService,
        private readonly ProjectWorkspaceService $workspaceService,
    ) {}

    /**
     * @param  array{
     *     customer_id?: string|null,
     *     currency_id?: string|null,
     *     name: string,
     *     description?: string|null,
     *     frequency: string,
     *     quantity: int|float|string,
     *     unit_price: int|float|string,
     *     invoice_due_days?: int|string|null,
     *     starts_on: string,
     *     next_run_on?: string|null,
     *     ends_on?: string|null,
     *     auto_create_invoice_draft?: bool,
     *     invoice_grouping?: string|null,
     *     status?: string|null
     * }  $attributes
     */
    public function create(
        Project $project,
        array $attributes,
        ?string $actorId = null,
    ): ProjectRecurringBilling {
        return DB::transaction(function () use ($project, $attributes, $actorId) {
            $schedule = ProjectRecurringBilling::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'customer_id' => $attributes['customer_id'] ?? $project->customer_id,
                'currency_id' => $attributes['currency_id'] ?? $project->currency_id,
                'name' => trim((string) $attributes['name']),
                'description' => filled($attributes['description'] ?? null)
                    ? trim((string) $attributes['description'])
                    : null,
                'frequency' => (string) $attributes['frequency'],
                'quantity' => round((float) $attributes['quantity'], 4),
                'unit_price' => round((float) $attributes['unit_price'], 2),
                'invoice_due_days' => max(0, (int) ($attributes['invoice_due_days'] ?? 30)),
                'starts_on' => (string) $attributes['starts_on'],
                'next_run_on' => (string) ($attributes['next_run_on'] ?? $attributes['starts_on']),
                'ends_on' => $attributes['ends_on'] ?? null,
                'auto_create_invoice_draft' => (bool) ($attributes['auto_create_invoice_draft'] ?? false),
                'invoice_grouping' => (string) ($attributes['invoice_grouping'] ?? ProjectInvoiceDraftService::GROUP_BY_PROJECT),
                'status' => (string) ($attributes['status'] ?? ProjectRecurringBilling::STATUS_DRAFT),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $schedule->fresh(['project', 'customer', 'currency']) ?? $schedule;
        });
    }

    /**
     * @param  array{
     *     customer_id?: string|null,
     *     currency_id?: string|null,
     *     name: string,
     *     description?: string|null,
     *     frequency: string,
     *     quantity: int|float|string,
     *     unit_price: int|float|string,
     *     invoice_due_days?: int|string|null,
     *     starts_on: string,
     *     next_run_on?: string|null,
     *     ends_on?: string|null,
     *     auto_create_invoice_draft?: bool,
     *     invoice_grouping?: string|null,
     *     status?: string|null
     * }  $attributes
     */
    public function update(
        ProjectRecurringBilling $schedule,
        array $attributes,
        ?string $actorId = null,
    ): ProjectRecurringBilling {
        return DB::transaction(function () use ($schedule, $attributes, $actorId) {
            $schedule = ProjectRecurringBilling::query()
                ->with('project')
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if ($schedule->status === ProjectRecurringBilling::STATUS_CANCELLED) {
                abort(422, 'Cancelled recurring billing schedules cannot be updated.');
            }

            $schedule->update([
                'customer_id' => $attributes['customer_id'] ?? $schedule->project?->customer_id,
                'currency_id' => $attributes['currency_id'] ?? $schedule->project?->currency_id,
                'name' => trim((string) $attributes['name']),
                'description' => filled($attributes['description'] ?? null)
                    ? trim((string) $attributes['description'])
                    : null,
                'frequency' => (string) $attributes['frequency'],
                'quantity' => round((float) $attributes['quantity'], 4),
                'unit_price' => round((float) $attributes['unit_price'], 2),
                'invoice_due_days' => max(0, (int) ($attributes['invoice_due_days'] ?? 30)),
                'starts_on' => (string) $attributes['starts_on'],
                'next_run_on' => (string) ($attributes['next_run_on'] ?? $schedule->next_run_on?->toDateString() ?? $attributes['starts_on']),
                'ends_on' => $attributes['ends_on'] ?? null,
                'auto_create_invoice_draft' => (bool) ($attributes['auto_create_invoice_draft'] ?? false),
                'invoice_grouping' => (string) ($attributes['invoice_grouping'] ?? ProjectInvoiceDraftService::GROUP_BY_PROJECT),
                'status' => (string) ($attributes['status'] ?? $schedule->status),
                'updated_by' => $actorId,
            ]);

            return $schedule->fresh(['project', 'customer', 'currency']) ?? $schedule;
        });
    }

    public function activate(
        ProjectRecurringBilling $schedule,
        ?string $actorId = null,
    ): ProjectRecurringBilling {
        return $this->transitionStatus(
            schedule: $schedule,
            status: ProjectRecurringBilling::STATUS_ACTIVE,
            actorId: $actorId,
            pausedAt: null,
        );
    }

    public function pause(
        ProjectRecurringBilling $schedule,
        ?string $actorId = null,
    ): ProjectRecurringBilling {
        return $this->transitionStatus(
            schedule: $schedule,
            status: ProjectRecurringBilling::STATUS_PAUSED,
            actorId: $actorId,
            pausedAt: now()->toIso8601String(),
        );
    }

    public function cancel(
        ProjectRecurringBilling $schedule,
        ?string $reason = null,
        ?string $actorId = null,
    ): ProjectRecurringBilling {
        return DB::transaction(function () use ($schedule, $reason, $actorId) {
            $schedule = ProjectRecurringBilling::query()
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            $schedule->update([
                'status' => ProjectRecurringBilling::STATUS_CANCELLED,
                'paused_at' => null,
                'cancelled_at' => now(),
                'cancellation_reason' => filled($reason) ? trim((string) $reason) : null,
                'updated_by' => $actorId,
            ]);

            return $schedule->fresh(['project', 'customer', 'currency']) ?? $schedule;
        });
    }

    /**
     * @return Collection<int, ProjectRecurringBillingRun>
     */
    public function processDueSchedules(
        ?string $companyId = null,
        ?CarbonImmutable $asOfDate = null,
        ?string $actorId = null,
    ): Collection {
        $processDate = ($asOfDate ?? CarbonImmutable::now())->startOfDay();

        $scheduleIds = ProjectRecurringBilling::query()
            ->where('status', ProjectRecurringBilling::STATUS_ACTIVE)
            ->when(
                filled($companyId),
                fn ($query) => $query->where('company_id', $companyId),
            )
            ->whereDate('next_run_on', '<=', $processDate->toDateString())
            ->orderBy('next_run_on')
            ->pluck('id');

        return $scheduleIds
            ->map(fn ($scheduleId) => $this->processScheduleById(
                scheduleId: (string) $scheduleId,
                asOfDate: $processDate,
                force: false,
                actorId: $actorId,
            ))
            ->filter(fn ($run) => $run instanceof ProjectRecurringBillingRun)
            ->values();
    }

    public function processSchedule(
        ProjectRecurringBilling $schedule,
        ?CarbonImmutable $asOfDate = null,
        bool $force = false,
        ?string $actorId = null,
    ): ?ProjectRecurringBillingRun {
        return $this->processScheduleById(
            scheduleId: (string) $schedule->id,
            asOfDate: ($asOfDate ?? CarbonImmutable::now())->startOfDay(),
            force: $force,
            actorId: $actorId,
        );
    }

    public function runNow(
        ProjectRecurringBilling $schedule,
        ?string $actorId = null,
    ): ?ProjectRecurringBillingRun {
        return $this->processSchedule(
            schedule: $schedule,
            asOfDate: CarbonImmutable::now()->startOfDay(),
            force: true,
            actorId: $actorId,
        );
    }

    private function processScheduleById(
        string $scheduleId,
        CarbonImmutable $asOfDate,
        bool $force,
        ?string $actorId = null,
    ): ?ProjectRecurringBillingRun {
        return DB::transaction(function () use ($scheduleId, $asOfDate, $force, $actorId) {
            $schedule = ProjectRecurringBilling::query()
                ->with(['project', 'customer', 'currency'])
                ->lockForUpdate()
                ->find($scheduleId);

            if (! $schedule) {
                return null;
            }

            if ($schedule->status !== ProjectRecurringBilling::STATUS_ACTIVE) {
                if (! $force) {
                    return null;
                }

                throw ValidationException::withMessages([
                    'recurring_billing' => 'Only active recurring billing schedules can run.',
                ]);
            }

            $dueOn = $schedule->next_run_on
                ? CarbonImmutable::parse($schedule->next_run_on)->startOfDay()
                : null;

            if (! $dueOn) {
                throw ValidationException::withMessages([
                    'recurring_billing' => 'Recurring billing schedule is missing its next run date.',
                ]);
            }

            if (! $force && $dueOn->gt($asOfDate)) {
                return null;
            }

            if ($schedule->ends_on && $dueOn->gt(CarbonImmutable::parse($schedule->ends_on)->startOfDay())) {
                $schedule->update([
                    'status' => ProjectRecurringBilling::STATUS_COMPLETED,
                    'updated_by' => $actorId,
                ]);

                return null;
            }

            $cycleKey = $dueOn->toDateString();
            $run = ProjectRecurringBillingRun::query()
                ->with(['billable', 'invoice'])
                ->firstOrNew([
                    'project_recurring_billing_id' => $schedule->id,
                    'cycle_key' => $cycleKey,
                ]);

            if (
                $run->exists
                && in_array($run->status, [
                    ProjectRecurringBillingRun::STATUS_READY,
                    ProjectRecurringBillingRun::STATUS_PENDING_APPROVAL,
                    ProjectRecurringBillingRun::STATUS_INVOICED,
                ], true)
            ) {
                $this->advanceSchedule($schedule, $dueOn, $actorId);
                $this->workspaceService->refreshProjectRollup($schedule->project);

                return $run->fresh(['billable', 'invoice', 'recurringBilling']) ?? $run;
            }

            $run->forceFill([
                'company_id' => $schedule->company_id,
                'project_id' => $schedule->project_id,
                'scheduled_for' => $cycleKey,
                'cycle_label' => $this->cycleLabel($schedule, $dueOn),
                'status' => $run->status ?: ProjectRecurringBillingRun::STATUS_READY,
                'error_message' => null,
                'updated_by' => $actorId,
            ]);

            if (! $run->exists) {
                $run->created_by = $actorId;
            }

            $run->saveQuietly();

            try {
                $billable = $this->createOrRefreshBillable($schedule, $run, $actorId);

                $run->forceFill([
                    'project_billable_id' => $billable->id,
                    'status' => $billable->approval_status === ProjectBillable::APPROVAL_STATUS_PENDING
                        ? ProjectRecurringBillingRun::STATUS_PENDING_APPROVAL
                        : ($billable->invoice_id
                            ? ProjectRecurringBillingRun::STATUS_INVOICED
                            : ProjectRecurringBillingRun::STATUS_READY),
                    'invoice_id' => $billable->invoice_id,
                    'processed_at' => now(),
                    'error_message' => null,
                    'updated_by' => $actorId,
                ])->saveQuietly();

                if (
                    $schedule->auto_create_invoice_draft
                    && $this->canAutoInvoice($billable)
                ) {
                    $invoice = $this->invoiceDraftService->createDrafts(
                        billableIds: [(string) $billable->id],
                        companyId: (string) $schedule->company_id,
                        groupBy: (string) $schedule->invoice_grouping,
                        actorId: $actorId,
                        invoiceDate: $dueOn->toDateString(),
                        dueDate: $dueOn->addDays(max((int) $schedule->invoice_due_days, 0))->toDateString(),
                        notesOverride: sprintf(
                            'Auto-generated from recurring billing schedule %s for %s.',
                            $schedule->name,
                            $run->cycle_label,
                        ),
                    )->first();

                    $run->forceFill([
                        'status' => ProjectRecurringBillingRun::STATUS_INVOICED,
                        'invoice_id' => $invoice?->id,
                        'processed_at' => now(),
                        'updated_by' => $actorId,
                    ])->saveQuietly();

                    $schedule->forceFill([
                        'last_invoice_id' => $invoice?->id,
                        'updated_by' => $actorId,
                    ])->saveQuietly();
                }

                $this->advanceSchedule($schedule, $dueOn, $actorId);
                $this->workspaceService->refreshProjectRollup($schedule->project);

                return $run->fresh(['billable', 'invoice', 'recurringBilling']) ?? $run;
            } catch (\Throwable $exception) {
                report($exception);

                $run->forceFill([
                    'status' => ProjectRecurringBillingRun::STATUS_FAILED,
                    'processed_at' => now(),
                    'error_message' => $exception->getMessage(),
                    'updated_by' => $actorId,
                ])->saveQuietly();

                $run = $run->fresh(['billable', 'invoice', 'recurringBilling', 'project']) ?? $run;
                $this->notificationService->notifyRecurringBillingFailure($run, $actorId);

                return $run;
            }
        });
    }

    private function transitionStatus(
        ProjectRecurringBilling $schedule,
        string $status,
        ?string $actorId = null,
        ?string $pausedAt = null,
    ): ProjectRecurringBilling {
        return DB::transaction(function () use ($schedule, $status, $actorId, $pausedAt) {
            $schedule = ProjectRecurringBilling::query()
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if ($schedule->status === ProjectRecurringBilling::STATUS_CANCELLED) {
                abort(422, 'Cancelled recurring billing schedules cannot change status.');
            }

            $schedule->update([
                'status' => $status,
                'paused_at' => $pausedAt,
                'updated_by' => $actorId,
            ]);

            return $schedule->fresh(['project', 'customer', 'currency']) ?? $schedule;
        });
    }

    private function createOrRefreshBillable(
        ProjectRecurringBilling $schedule,
        ProjectRecurringBillingRun $run,
        ?string $actorId = null,
    ): ProjectBillable {
        $customerId = (string) ($schedule->customer_id ?? $schedule->project?->customer_id ?? '');
        $currencyId = (string) ($schedule->currency_id ?? $schedule->project?->currency_id ?? '');

        if ($customerId === '' || $currencyId === '') {
            throw ValidationException::withMessages([
                'recurring_billing' => 'Recurring billing schedules need both customer and currency before processing.',
            ]);
        }

        $billable = ProjectBillable::query()
            ->withTrashed()
            ->firstOrNew([
                'source_type' => ProjectRecurringBillingRun::class,
                'source_id' => $run->id,
            ]);

        if ($billable->trashed()) {
            $billable->restore();
        }

        if ($billable->invoice_id || $billable->status === ProjectBillable::STATUS_INVOICED) {
            return $billable->fresh(['project', 'customer', 'currency']) ?? $billable;
        }

        $amount = round((float) $schedule->quantity * (float) $schedule->unit_price, 2);
        $requiresApproval = $this->approvalPolicyService->requiresApproval(
            (string) $schedule->company_id,
            $amount,
        );

        $billable->forceFill([
            'company_id' => $schedule->company_id,
            'project_id' => $schedule->project_id,
            'billable_type' => ProjectBillable::TYPE_RECURRING,
            'source_type' => ProjectRecurringBillingRun::class,
            'source_id' => $run->id,
            'customer_id' => $customerId,
            'description' => $this->billableDescription($schedule, $run),
            'quantity' => round((float) $schedule->quantity, 4),
            'unit_price' => round((float) $schedule->unit_price, 2),
            'amount' => $amount,
            'currency_id' => $currencyId,
            'status' => ProjectBillable::STATUS_READY,
            'approval_status' => $requiresApproval
                ? ProjectBillable::APPROVAL_STATUS_PENDING
                : ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'updated_by' => $actorId,
        ]);

        if (! $billable->exists) {
            $billable->created_by = $actorId;
        }

        $billable->saveQuietly();

        return $billable->fresh(['project', 'customer', 'currency']) ?? $billable;
    }

    private function canAutoInvoice(ProjectBillable $billable): bool
    {
        return in_array($billable->status, [
            ProjectBillable::STATUS_READY,
            ProjectBillable::STATUS_APPROVED,
        ], true)
            && ! in_array($billable->approval_status, [
                ProjectBillable::APPROVAL_STATUS_PENDING,
                ProjectBillable::APPROVAL_STATUS_REJECTED,
            ], true)
            && ! $billable->invoice_id;
    }

    private function advanceSchedule(
        ProjectRecurringBilling $schedule,
        CarbonImmutable $processedDueOn,
        ?string $actorId = null,
    ): void {
        $nextRunOn = $this->nextRunDate($schedule, $processedDueOn);
        $isCompleted = $schedule->ends_on
            && $nextRunOn->gt(CarbonImmutable::parse($schedule->ends_on)->startOfDay());

        $schedule->forceFill([
            'next_run_on' => $nextRunOn->toDateString(),
            'last_run_at' => now(),
            'status' => $isCompleted
                ? ProjectRecurringBilling::STATUS_COMPLETED
                : ProjectRecurringBilling::STATUS_ACTIVE,
            'updated_by' => $actorId,
        ])->saveQuietly();
    }

    private function nextRunDate(
        ProjectRecurringBilling $schedule,
        CarbonImmutable $currentRunDate,
    ): CarbonImmutable {
        return match ($schedule->frequency) {
            ProjectRecurringBilling::FREQUENCY_WEEKLY => $currentRunDate->addWeek(),
            ProjectRecurringBilling::FREQUENCY_QUARTERLY => $currentRunDate->addMonthsNoOverflow(3),
            ProjectRecurringBilling::FREQUENCY_YEARLY => $currentRunDate->addYearNoOverflow(),
            default => $currentRunDate->addMonthNoOverflow(),
        };
    }

    private function cycleLabel(
        ProjectRecurringBilling $schedule,
        CarbonImmutable $dueOn,
    ): string {
        return match ($schedule->frequency) {
            ProjectRecurringBilling::FREQUENCY_WEEKLY => 'Week of '.$dueOn->toDateString(),
            ProjectRecurringBilling::FREQUENCY_QUARTERLY => sprintf('Q%d %s', $dueOn->quarter, $dueOn->format('Y')),
            ProjectRecurringBilling::FREQUENCY_YEARLY => $dueOn->format('Y'),
            default => $dueOn->format('F Y'),
        };
    }

    private function billableDescription(
        ProjectRecurringBilling $schedule,
        ProjectRecurringBillingRun $run,
    ): string {
        $baseDescription = filled($schedule->description)
            ? trim((string) $schedule->description)
            : trim((string) $schedule->name);

        return trim($baseDescription.' ('.$run->cycle_label.')');
    }
}
