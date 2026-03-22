<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectMilestoneStoreRequest;
use App\Http\Requests\Projects\ProjectMilestoneUpdateRequest;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\ProjectMilestoneWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectMilestonesController extends Controller
{
    public function create(Project $project, Request $request): Response
    {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectMilestone::class);
        abort_unless($request->user()?->can('update', $project), 403);

        return Inertia::render('projects/milestones/create', [
            'project' => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
            ],
            'milestone' => [
                'name' => '',
                'description' => '',
                'sequence' => (string) ($project->milestones()->max('sequence') + 1),
                'status' => ProjectMilestone::STATUS_DRAFT,
                'due_date' => '',
                'amount' => '',
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(
        ProjectMilestoneStoreRequest $request,
        Project $project,
        ProjectMilestoneWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectMilestone::class);
        abort_unless($request->user()?->can('update', $project), 403);

        $milestone = $workflowService->create(
            project: $project,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.milestones.edit', $milestone)
            ->with('success', 'Milestone created.');
    }

    public function edit(ProjectMilestone $milestone, Request $request): Response
    {
        $this->authorize('view', $milestone);

        $milestone->load([
            'project:id,project_code,name',
            'approvedBy:id,name',
        ]);

        return Inertia::render('projects/milestones/edit', [
            'project' => [
                'id' => $milestone->project->id,
                'project_code' => $milestone->project->project_code,
                'name' => $milestone->project->name,
            ],
            'milestone' => [
                'id' => $milestone->id,
                'name' => $milestone->name,
                'description' => $milestone->description,
                'sequence' => (string) $milestone->sequence,
                'status' => $milestone->status,
                'due_date' => $milestone->due_date?->toDateString(),
                'completed_at' => $milestone->completed_at?->toIso8601String(),
                'approved_by_name' => $milestone->approvedBy?->name,
                'approved_at' => $milestone->approved_at?->toIso8601String(),
                'amount' => (string) $milestone->amount,
                'invoice_status' => $milestone->invoice_status,
            ],
            'statusOptions' => $this->statusOptions($milestone->status),
            'abilities' => [
                'can_edit_milestone' => $request->user()?->can('update', $milestone) ?? false,
                'can_delete_milestone' => $request->user()?->can('delete', $milestone) ?? false,
            ],
        ]);
    }

    public function update(
        ProjectMilestoneUpdateRequest $request,
        ProjectMilestone $milestone,
        ProjectMilestoneWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $milestone);

        $milestone = $workflowService->update(
            milestone: $milestone,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.milestones.edit', $milestone)
            ->with('success', 'Milestone updated.');
    }

    public function destroy(
        ProjectMilestone $milestone,
        ProjectMilestoneWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('delete', $milestone);

        $project = $milestone->project;

        $workflowService->delete($milestone);

        return redirect()
            ->route('company.projects.show', $project)
            ->with('success', 'Milestone deleted.');
    }

    /**
     * @return array<int, string>
     */
    private function statusOptions(?string $currentStatus = null): array
    {
        $statuses = array_values(array_filter(
            ProjectMilestone::STATUSES,
            fn (string $status) => $status !== ProjectMilestone::STATUS_BILLED,
        ));

        if ($currentStatus && ! in_array($currentStatus, $statuses, true)) {
            $statuses[] = $currentStatus;
        }

        return $statuses;
    }
}
