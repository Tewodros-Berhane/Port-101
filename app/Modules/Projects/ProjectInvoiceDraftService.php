<?php

namespace App\Modules\Projects;

use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectTimesheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectInvoiceDraftService
{
    public const GROUP_BY_PROJECT = 'project';

    public const GROUP_BY_CUSTOMER = 'customer';

    /**
     * @var array<int, string>
     */
    public const GROUP_BY_OPTIONS = [
        self::GROUP_BY_PROJECT,
        self::GROUP_BY_CUSTOMER,
    ];

    public function __construct(
        private readonly AccountingInvoiceWorkflowService $invoiceWorkflowService,
    ) {}

    /**
     * @param  array<int, string>  $billableIds
     * @return Collection<int, AccountingInvoice>
     */
    public function createDrafts(
        array $billableIds,
        string $companyId,
        string $groupBy = self::GROUP_BY_PROJECT,
        ?string $actorId = null,
    ): Collection {
        $selectedIds = collect($billableIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'billable_ids' => 'Select at least one billable to create an invoice draft.',
            ]);
        }

        if (! in_array($groupBy, self::GROUP_BY_OPTIONS, true)) {
            throw ValidationException::withMessages([
                'group_by' => 'Invalid project billing grouping option.',
            ]);
        }

        return DB::transaction(function () use ($selectedIds, $companyId, $groupBy, $actorId) {
            $billables = ProjectBillable::query()
                ->with([
                    'project:id,project_code,name,customer_id,currency_id',
                    'currency:id,code',
                ])
                ->where('company_id', $companyId)
                ->whereIn('id', $selectedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ProjectBillable $billable) => (string) $billable->id);

            if ($billables->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'billable_ids' => 'One or more selected billables could not be found in the active company.',
                ]);
            }

            /** @var Collection<int, ProjectBillable> $orderedBillables */
            $orderedBillables = $selectedIds
                ->map(fn (string $id) => $billables->get($id))
                ->filter();

            $orderedBillables->each(fn (ProjectBillable $billable) => $this->assertInvoiceEligible($billable));

            /** @var Collection<int, AccountingInvoice> $createdInvoices */
            $createdInvoices = collect();

            foreach ($this->groupBillables($orderedBillables, $groupBy) as $groupedBillables) {
                $group = $groupedBillables->values();
                $firstBillable = $group->first();

                if (! $firstBillable instanceof ProjectBillable) {
                    continue;
                }

                $partnerId = (string) ($firstBillable->customer_id ?? $firstBillable->project?->customer_id ?? '');
                $currencyCode = (string) ($firstBillable->currency?->code ?? '');

                if ($partnerId === '') {
                    throw ValidationException::withMessages([
                        'billable_ids' => 'Selected billables must be linked to a customer before invoicing.',
                    ]);
                }

                if ($currencyCode === '') {
                    throw ValidationException::withMessages([
                        'billable_ids' => 'Selected billables must have a currency before invoicing.',
                    ]);
                }

                $invoice = $this->invoiceWorkflowService->createDraft(
                    attributes: [
                        'partner_id' => $partnerId,
                        'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
                        'sales_order_id' => null,
                        'invoice_date' => now()->toDateString(),
                        'due_date' => now()->addDays(30)->toDateString(),
                        'currency_code' => $currencyCode,
                        'notes' => $this->invoiceNotes($group, $groupBy),
                        'lines' => $group
                            ->map(fn (ProjectBillable $billable) => [
                                'product_id' => null,
                                'description' => $this->invoiceLineDescription($billable),
                                'quantity' => round((float) $billable->quantity, 4),
                                'unit_price' => round((float) $billable->unit_price, 2),
                                'tax_rate' => 0,
                            ])
                            ->values()
                            ->all(),
                    ],
                    companyId: $companyId,
                    actorId: $actorId,
                );

                foreach ($group->values() as $index => $billable) {
                    $billable->update([
                        'status' => ProjectBillable::STATUS_INVOICED,
                        'invoice_id' => $invoice->id,
                        'invoice_line_reference' => $this->invoiceLineReference(
                            (string) $invoice->invoice_number,
                            $index + 1,
                        ),
                        'updated_by' => $actorId,
                    ]);

                    $this->markSourceAsInvoiced($billable, $actorId);
                }

                $createdInvoices->push($invoice->fresh(['lines']) ?? $invoice);
            }

            return $createdInvoices;
        });
    }

    /**
     * @param  Collection<int, ProjectBillable>  $billables
     * @return Collection<int, Collection<int, ProjectBillable>>
     */
    private function groupBillables(Collection $billables, string $groupBy): Collection
    {
        return $billables
            ->groupBy(function (ProjectBillable $billable) use ($groupBy): string {
                $customerId = (string) ($billable->customer_id ?? $billable->project?->customer_id ?? 'customer');
                $currencyId = (string) ($billable->currency_id ?? $billable->project?->currency_id ?? 'currency');
                $projectId = $groupBy === self::GROUP_BY_PROJECT
                    ? (string) $billable->project_id
                    : 'all-projects';

                return implode('|', [$customerId, $currencyId, $projectId]);
            })
            ->map(fn (Collection $group) => $group->values())
            ->values();
    }

    private function assertInvoiceEligible(ProjectBillable $billable): void
    {
        if ($billable->invoice_id || $billable->status === ProjectBillable::STATUS_INVOICED) {
            throw ValidationException::withMessages([
                'billable_ids' => 'One or more selected billables are already linked to an invoice.',
            ]);
        }

        if ($billable->status === ProjectBillable::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'billable_ids' => 'Cancelled billables cannot be handed off to Accounting.',
            ]);
        }

        if ($billable->approval_status === ProjectBillable::APPROVAL_STATUS_PENDING) {
            throw ValidationException::withMessages([
                'billable_ids' => 'Pending-approval billables must be approved before invoicing.',
            ]);
        }

        if ($billable->approval_status === ProjectBillable::APPROVAL_STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'billable_ids' => 'Rejected billables cannot be invoiced.',
            ]);
        }

        if (! in_array($billable->status, [
            ProjectBillable::STATUS_READY,
            ProjectBillable::STATUS_APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'billable_ids' => 'Only ready or approved billables can be invoiced.',
            ]);
        }
    }

    /**
     * @param  Collection<int, ProjectBillable>  $billables
     */
    private function invoiceNotes(Collection $billables, string $groupBy): string
    {
        $projectCodes = $billables
            ->map(fn (ProjectBillable $billable) => $billable->project?->project_code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($projectCodes === []) {
            return 'Auto-generated from Projects billing queue.';
        }

        $projectSummary = implode(', ', array_slice($projectCodes, 0, 5));

        if (count($projectCodes) > 5) {
            $projectSummary .= ' and '.(count($projectCodes) - 5).' more';
        }

        if ($groupBy === self::GROUP_BY_PROJECT && count($projectCodes) === 1) {
            return 'Auto-generated from Projects billing queue for project '.$projectSummary.'.';
        }

        return 'Auto-generated from Projects billing queue for projects '.$projectSummary.'.';
    }

    private function invoiceLineDescription(ProjectBillable $billable): string
    {
        $description = trim((string) $billable->description);
        $projectCode = trim((string) ($billable->project?->project_code ?? ''));

        if ($projectCode === '') {
            return $description !== ''
                ? $description
                : ucfirst(str_replace('_', ' ', (string) $billable->billable_type));
        }

        if ($description === '') {
            return '['.$projectCode.'] '.ucfirst(str_replace('_', ' ', (string) $billable->billable_type));
        }

        return '['.$projectCode.'] '.$description;
    }

    private function invoiceLineReference(string $invoiceNumber, int $lineNumber): string
    {
        return sprintf('%s-L%02d', $invoiceNumber, $lineNumber);
    }

    private function markSourceAsInvoiced(ProjectBillable $billable, ?string $actorId = null): void
    {
        if ($billable->source_type === ProjectTimesheet::class && $billable->source_id) {
            ProjectTimesheet::query()
                ->where('company_id', $billable->company_id)
                ->where('id', $billable->source_id)
                ->update([
                    'invoice_status' => ProjectTimesheet::INVOICE_STATUS_INVOICED,
                    'updated_by' => $actorId,
                ]);

            return;
        }

        if ($billable->source_type === ProjectMilestone::class && $billable->source_id) {
            ProjectMilestone::query()
                ->where('company_id', $billable->company_id)
                ->where('id', $billable->source_id)
                ->update([
                    'status' => ProjectMilestone::STATUS_BILLED,
                    'invoice_status' => ProjectMilestone::INVOICE_STATUS_INVOICED,
                    'updated_by' => $actorId,
                ]);
        }
    }
}
