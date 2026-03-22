<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectTimesheet;
use Illuminate\Support\Collection;

class ProjectProfitabilityService
{
    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<string, array{
     *     budget_hours: float|null,
     *     logged_hours: float,
     *     approved_hours: float,
     *     remaining_hours: float|null,
     *     utilization_percent: float|null,
     *     budget_amount: float|null,
     *     actual_cost_amount: float,
     *     budget_consumed_percent: float|null,
     *     billable_pipeline_amount: float,
     *     ready_to_invoice_amount: float,
     *     pending_approval_amount: float,
     *     invoiced_amount: float,
     *     gross_margin_amount: float,
     *     gross_margin_percent: float|null,
     *     realization_percent: float|null
     * }>
     */
    public function summarizeProjects(Collection $projects): Collection
    {
        $projects = $projects
            ->filter(fn ($project) => $project instanceof Project)
            ->keyBy(fn (Project $project) => (string) $project->id);

        if ($projects->isEmpty()) {
            return collect();
        }

        $projectIds = $projects->keys()->all();

        $loggedHours = ProjectTimesheet::query()
            ->selectRaw('project_id, COALESCE(SUM(hours), 0) as aggregate')
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        $approvedHours = ProjectTimesheet::query()
            ->selectRaw('project_id, COALESCE(SUM(hours), 0) as aggregate')
            ->whereIn('project_id', $projectIds)
            ->where('approval_status', ProjectTimesheet::APPROVAL_STATUS_APPROVED)
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        $pipelineAmounts = ProjectBillable::query()
            ->selectRaw('project_id, COALESCE(SUM(amount), 0) as aggregate')
            ->whereIn('project_id', $projectIds)
            ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        $readyToInvoiceAmounts = ProjectBillable::query()
            ->selectRaw('project_id, COALESCE(SUM(amount), 0) as aggregate')
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', [
                ProjectBillable::STATUS_READY,
                ProjectBillable::STATUS_APPROVED,
            ])
            ->whereNotIn('approval_status', [
                ProjectBillable::APPROVAL_STATUS_PENDING,
                ProjectBillable::APPROVAL_STATUS_REJECTED,
            ])
            ->whereNull('invoice_id')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        $pendingApprovalAmounts = ProjectBillable::query()
            ->selectRaw('project_id, COALESCE(SUM(amount), 0) as aggregate')
            ->whereIn('project_id', $projectIds)
            ->where('approval_status', ProjectBillable::APPROVAL_STATUS_PENDING)
            ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        $invoicedAmounts = ProjectBillable::query()
            ->selectRaw('project_id, COALESCE(SUM(amount), 0) as aggregate')
            ->whereIn('project_id', $projectIds)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('invoice_id')
                    ->orWhere('status', ProjectBillable::STATUS_INVOICED);
            })
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        return $projects->mapWithKeys(function (Project $project) use (
            $loggedHours,
            $approvedHours,
            $pipelineAmounts,
            $readyToInvoiceAmounts,
            $pendingApprovalAmounts,
            $invoicedAmounts,
        ): array {
            $projectId = (string) $project->id;
            $budgetHours = $project->budget_hours !== null
                ? round((float) $project->budget_hours, 2)
                : null;
            $budgetAmount = $project->budget_amount !== null
                ? round((float) $project->budget_amount, 2)
                : null;
            $actualCostAmount = round((float) $project->actual_cost_amount, 2);
            $loggedHoursValue = round((float) ($loggedHours[$projectId] ?? 0), 2);
            $approvedHoursValue = round((float) ($approvedHours[$projectId] ?? 0), 2);
            $pipelineAmount = round((float) ($pipelineAmounts[$projectId] ?? 0), 2);
            $readyToInvoiceAmount = round((float) ($readyToInvoiceAmounts[$projectId] ?? 0), 2);
            $pendingApprovalAmount = round((float) ($pendingApprovalAmounts[$projectId] ?? 0), 2);
            $invoicedAmount = round((float) ($invoicedAmounts[$projectId] ?? 0), 2);
            $grossMarginAmount = round($pipelineAmount - $actualCostAmount, 2);

            return [
                $projectId => [
                    'budget_hours' => $budgetHours,
                    'logged_hours' => $loggedHoursValue,
                    'approved_hours' => $approvedHoursValue,
                    'remaining_hours' => $budgetHours !== null
                        ? round($budgetHours - $loggedHoursValue, 2)
                        : null,
                    'utilization_percent' => $budgetHours && $budgetHours > 0
                        ? round(($loggedHoursValue / $budgetHours) * 100, 2)
                        : null,
                    'budget_amount' => $budgetAmount,
                    'actual_cost_amount' => $actualCostAmount,
                    'budget_consumed_percent' => $budgetAmount && $budgetAmount > 0
                        ? round(($actualCostAmount / $budgetAmount) * 100, 2)
                        : null,
                    'billable_pipeline_amount' => $pipelineAmount,
                    'ready_to_invoice_amount' => $readyToInvoiceAmount,
                    'pending_approval_amount' => $pendingApprovalAmount,
                    'invoiced_amount' => $invoicedAmount,
                    'gross_margin_amount' => $grossMarginAmount,
                    'gross_margin_percent' => $pipelineAmount > 0
                        ? round(($grossMarginAmount / $pipelineAmount) * 100, 2)
                        : null,
                    'realization_percent' => $pipelineAmount > 0
                        ? round(($invoicedAmount / $pipelineAmount) * 100, 2)
                        : null,
                ],
            ];
        });
    }

    /**
     * @return array{
     *     budget_hours: float|null,
     *     logged_hours: float,
     *     approved_hours: float,
     *     remaining_hours: float|null,
     *     utilization_percent: float|null,
     *     budget_amount: float|null,
     *     actual_cost_amount: float,
     *     budget_consumed_percent: float|null,
     *     billable_pipeline_amount: float,
     *     ready_to_invoice_amount: float,
     *     pending_approval_amount: float,
     *     invoiced_amount: float,
     *     gross_margin_amount: float,
     *     gross_margin_percent: float|null,
     *     realization_percent: float|null
     * }
     */
    public function summarizeProject(Project $project): array
    {
        return $this->summarizeProjects(collect([$project]))
            ->get((string) $project->id, $this->emptyProjectSummary());
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array{
     *     total_budget_hours: float,
     *     total_logged_hours: float,
     *     utilization_percent: float|null,
     *     billable_pipeline_amount: float,
     *     ready_to_invoice_amount: float,
     *     pending_approval_amount: float,
     *     invoiced_amount: float,
     *     gross_margin_amount: float,
     *     gross_margin_percent: float|null,
     *     negative_margin_projects: int,
     *     over_budget_hour_projects: int
     * }
     */
    public function summarizePortfolio(Collection $projects): array
    {
        $projectSummaries = $this->summarizeProjects($projects);

        if ($projectSummaries->isEmpty()) {
            return [
                'total_budget_hours' => 0,
                'total_logged_hours' => 0,
                'utilization_percent' => null,
                'billable_pipeline_amount' => 0,
                'ready_to_invoice_amount' => 0,
                'pending_approval_amount' => 0,
                'invoiced_amount' => 0,
                'gross_margin_amount' => 0,
                'gross_margin_percent' => null,
                'negative_margin_projects' => 0,
                'over_budget_hour_projects' => 0,
            ];
        }

        $totalBudgetHours = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['budget_hours'] ?? 0)
                ->sum(),
            2,
        );
        $totalLoggedHours = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['logged_hours'])
                ->sum(),
            2,
        );
        $billablePipelineAmount = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['billable_pipeline_amount'])
                ->sum(),
            2,
        );
        $readyToInvoiceAmount = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['ready_to_invoice_amount'])
                ->sum(),
            2,
        );
        $pendingApprovalAmount = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['pending_approval_amount'])
                ->sum(),
            2,
        );
        $invoicedAmount = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['invoiced_amount'])
                ->sum(),
            2,
        );
        $grossMarginAmount = round(
            (float) $projectSummaries
                ->map(fn (array $summary) => $summary['gross_margin_amount'])
                ->sum(),
            2,
        );

        return [
            'total_budget_hours' => $totalBudgetHours,
            'total_logged_hours' => $totalLoggedHours,
            'utilization_percent' => $totalBudgetHours > 0
                ? round(($totalLoggedHours / $totalBudgetHours) * 100, 2)
                : null,
            'billable_pipeline_amount' => $billablePipelineAmount,
            'ready_to_invoice_amount' => $readyToInvoiceAmount,
            'pending_approval_amount' => $pendingApprovalAmount,
            'invoiced_amount' => $invoicedAmount,
            'gross_margin_amount' => $grossMarginAmount,
            'gross_margin_percent' => $billablePipelineAmount > 0
                ? round(($grossMarginAmount / $billablePipelineAmount) * 100, 2)
                : null,
            'negative_margin_projects' => $projectSummaries
                ->filter(fn (array $summary) => $summary['gross_margin_amount'] < 0)
                ->count(),
            'over_budget_hour_projects' => $projectSummaries
                ->filter(fn (array $summary) => $summary['budget_hours'] !== null
                    && $summary['logged_hours'] > (float) $summary['budget_hours'])
                ->count(),
        ];
    }

    /**
     * @return array{
     *     budget_hours: float|null,
     *     logged_hours: float,
     *     approved_hours: float,
     *     remaining_hours: float|null,
     *     utilization_percent: float|null,
     *     budget_amount: float|null,
     *     actual_cost_amount: float,
     *     budget_consumed_percent: float|null,
     *     billable_pipeline_amount: float,
     *     ready_to_invoice_amount: float,
     *     pending_approval_amount: float,
     *     invoiced_amount: float,
     *     gross_margin_amount: float,
     *     gross_margin_percent: float|null,
     *     realization_percent: float|null
     * }
     */
    private function emptyProjectSummary(): array
    {
        return [
            'budget_hours' => null,
            'logged_hours' => 0,
            'approved_hours' => 0,
            'remaining_hours' => null,
            'utilization_percent' => null,
            'budget_amount' => null,
            'actual_cost_amount' => 0,
            'budget_consumed_percent' => null,
            'billable_pipeline_amount' => 0,
            'ready_to_invoice_amount' => 0,
            'pending_approval_amount' => 0,
            'invoiced_amount' => 0,
            'gross_margin_amount' => 0,
            'gross_margin_percent' => null,
            'realization_percent' => null,
        ];
    }
}
