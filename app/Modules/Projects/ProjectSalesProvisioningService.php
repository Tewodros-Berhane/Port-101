<?php

namespace App\Modules\Projects;

use App\Core\MasterData\Models\Currency;
use App\Modules\Integrations\OutboundEventService;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectSalesProvisioningService
{
    public function __construct(
        private readonly ProjectWorkspaceService $workspaceService,
        private readonly ProjectNotificationService $notificationService,
        private readonly OutboundEventService $outboundEventService,
    ) {}

    public function createOrRefreshFromSalesOrder(string $companyId, string $orderId): ?Project
    {
        $order = SalesOrder::query()
            ->with([
                'company:id,currency_code',
                'partner:id,name',
                'lines.product:id,name,type',
            ])
            ->where('company_id', $companyId)
            ->find($orderId);

        if (! $order) {
            return null;
        }

        $serviceLines = $order->lines
            ->filter(fn (SalesOrderLine $line) => ($line->product?->type ?? null) === 'service')
            ->values();

        if ($serviceLines->isEmpty()) {
            return null;
        }

        $result = DB::transaction(function () use ($order, $serviceLines) {
            $actorId = $this->actorIdForOrder($order);
            $currency = $this->resolveCurrency($order, $actorId);
            $project = Project::query()
                ->where('company_id', $order->company_id)
                ->where('sales_order_id', $order->id)
                ->first();
            $wasNew = ! $project;

            if (! $project) {
                $project = new Project;
                $project->created_by = $actorId;
                $project->project_code = $this->generateProjectCode($order);
            }

            $project->forceFill([
                'company_id' => $order->company_id,
                'customer_id' => $order->partner_id,
                'sales_order_id' => $order->id,
                'currency_id' => $currency->id,
                'name' => $this->projectName($order),
                'description' => $this->projectDescription($order, $serviceLines),
                'status' => Project::STATUS_ACTIVE,
                'billing_type' => $order->lines->count() === $serviceLines->count()
                    ? Project::BILLING_TYPE_TIME_AND_MATERIAL
                    : Project::BILLING_TYPE_MIXED,
                'project_manager_id' => $order->confirmed_by ?? $order->created_by,
                'start_date' => $order->confirmed_at?->toDateString()
                    ?? $order->order_date?->toDateString()
                    ?? now()->toDateString(),
                'target_end_date' => $order->order_date?->copy()->addDays(30)->toDateString(),
                'budget_amount' => round(
                    (float) $serviceLines->sum(fn (SalesOrderLine $line) => (float) $line->line_total),
                    2,
                ),
                'updated_by' => $actorId,
            ]);

            $project->save();

            $stages = $this->workspaceService->ensureDefaultStages(
                companyId: (string) $order->company_id,
                actorId: $actorId,
            );

            $this->workspaceService->syncProjectMembers($project, $actorId);
            $this->createInitialTasks(
                project: $project,
                serviceLines: $serviceLines,
                stageId: (string) ($stages->first()?->id ?? ''),
                actorId: $actorId,
            );
            $this->workspaceService->refreshProjectRollup($project);

            $project = $project->fresh([
                'customer:id,name',
                'salesOrder:id,order_number',
                'currency:id,code',
                'projectManager:id,name,email',
            ]) ?? $project;

            return [
                'project' => $project,
                'was_new' => $wasNew,
                'actor_id' => $actorId,
            ];
        });

        /** @var Project $project */
        $project = $result['project'];
        $wasNew = (bool) $result['was_new'];
        $actorId = $result['actor_id'];

        if ($wasNew) {
            $this->notificationService->notifyProjectProvisioned(
                project: $project,
                order: $order,
                actorId: $actorId,
            );

            $this->outboundEventService->record(
                companyId: (string) $project->company_id,
                eventType: WebhookEventCatalog::PROJECT_PROVISIONED,
                aggregateType: Project::class,
                aggregateId: (string) $project->id,
                data: [
                    'object_type' => 'project',
                    'object_id' => (string) $project->id,
                    'project_code' => (string) $project->project_code,
                    'name' => (string) $project->name,
                    'status' => (string) $project->status,
                    'billing_type' => (string) $project->billing_type,
                    'customer_id' => $project->customer_id ? (string) $project->customer_id : null,
                    'customer_name' => $project->customer?->name,
                    'sales_order_id' => $order->id,
                    'sales_order_number' => $order->order_number,
                    'project_manager_id' => $project->project_manager_id ? (string) $project->project_manager_id : null,
                    'start_date' => $project->start_date?->toDateString(),
                    'target_end_date' => $project->target_end_date?->toDateString(),
                    'budget_amount' => (float) $project->budget_amount,
                ],
                actorId: $actorId,
            );
        }

        return $project;
    }

    private function resolveCurrency(SalesOrder $order, ?string $actorId = null): Currency
    {
        $preferredCode = strtoupper((string) ($order->company?->currency_code ?: 'USD'));

        $currency = Currency::query()
            ->where('company_id', $order->company_id)
            ->where('code', $preferredCode)
            ->first();

        if ($currency) {
            return $currency;
        }

        $currency = Currency::query()
            ->where('company_id', $order->company_id)
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->first();

        if ($currency) {
            return $currency;
        }

        return Currency::create([
            'company_id' => $order->company_id,
            'code' => $preferredCode,
            'name' => $preferredCode.' Currency',
            'symbol' => $preferredCode === 'USD' ? '$' : null,
            'decimal_places' => 2,
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * @param  Collection<int, SalesOrderLine>  $serviceLines
     */
    private function createInitialTasks(
        Project $project,
        Collection $serviceLines,
        string $stageId,
        ?string $actorId = null,
    ): void {
        if ($project->tasks()->exists()) {
            return;
        }

        $serviceLines->values()->each(function (SalesOrderLine $line, int $index) use (
            $project,
            $stageId,
            $actorId
        ): void {
            $task = ProjectTask::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'stage_id' => $stageId !== '' ? $stageId : null,
                'customer_id' => $project->customer_id,
                'task_number' => $this->generateTaskNumber($project, $index + 1),
                'title' => $line->product?->name
                    ?: Str::limit(trim((string) $line->description), 120, ''),
                'description' => filled($line->description)
                    ? trim((string) $line->description)
                    : null,
                'status' => ProjectTask::STATUS_TODO,
                'priority' => ProjectTask::PRIORITY_MEDIUM,
                'assigned_to' => $project->project_manager_id,
                'start_date' => $project->start_date?->toDateString(),
                'due_date' => $project->target_end_date?->toDateString(),
                'estimated_hours' => max(round((float) $line->quantity, 2), 0),
                'actual_hours' => 0,
                'is_billable' => true,
                'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->workspaceService->syncTaskAssigneeMember(
                project: $project,
                assigneeId: $task->assigned_to ? (string) $task->assigned_to : null,
                actorId: $actorId,
            );
        });
    }

    private function generateProjectCode(SalesOrder $order): string
    {
        $base = Str::of('PRJ-'.$order->order_number)
            ->upper()
            ->replaceMatches('/[^A-Z0-9-]+/', '-')
            ->trim('-')
            ->value();

        $candidate = Str::limit($base, 64, '');
        $suffix = 1;

        while (
            Project::query()
                ->where('company_id', $order->company_id)
                ->where('project_code', $candidate)
                ->exists()
        ) {
            $suffix++;
            $candidate = Str::limit($base, 64 - strlen('-'.$suffix), '').'-'.$suffix;
        }

        return $candidate;
    }

    private function generateTaskNumber(Project $project, int $sequence): string
    {
        $base = Str::limit((string) $project->project_code, 58, '');

        return sprintf('%s-T%02d', $base, $sequence);
    }

    private function actorIdForOrder(SalesOrder $order): ?string
    {
        return $order->confirmed_by
            ? (string) $order->confirmed_by
            : ($order->updated_by
                ? (string) $order->updated_by
                : ($order->created_by ? (string) $order->created_by : null));
    }

    private function projectName(SalesOrder $order): string
    {
        $customerName = trim((string) ($order->partner?->name ?? 'Customer'));

        return trim($customerName.' delivery project ('.$order->order_number.')');
    }

    /**
     * @param  Collection<int, SalesOrderLine>  $serviceLines
     */
    private function projectDescription(SalesOrder $order, Collection $serviceLines): string
    {
        return sprintf(
            'Auto-provisioned from confirmed sales order %s with %d service line(s).',
            $order->order_number,
            $serviceLines->count(),
        );
    }
}
