<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()?->hasPermission('projects.projects.view'),
            403,
        );

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $projectQuery = Project::query()
            ->with(['customer:id,name', 'projectManager:id,name'])
            ->accessibleTo($user);

        $taskQuery = ProjectTask::query()
            ->with([
                'project:id,name,project_code',
                'assignee:id,name',
            ])
            ->whereHas('project', function (Builder $builder) use ($user): void {
                $builder->accessibleTo($user);
            });

        $today = now()->toDateString();
        $dueWindowEnd = now()->addDays(7)->toDateString();
        $canViewTasks = $user->hasPermission('projects.tasks.view');

        $recentProjects = (clone $projectQuery)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'status' => $project->status,
                'health_status' => $project->health_status,
                'customer_name' => $project->customer?->name,
                'project_manager_name' => $project->projectManager?->name,
                'progress_percent' => (float) $project->progress_percent,
                'target_end_date' => $project->target_end_date?->toDateString(),
                'can_edit' => $user->can('update', $project),
            ])
            ->values()
            ->all();

        $recentTasks = $canViewTasks
            ? (clone $taskQuery)
                ->latest('updated_at')
                ->limit(6)
                ->get()
                ->map(fn (ProjectTask $task) => [
                    'id' => $task->id,
                    'task_number' => $task->task_number,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project?->name,
                    'project_code' => $task->project?->project_code,
                    'assignee_name' => $task->assignee?->name,
                    'due_date' => $task->due_date?->toDateString(),
                    'can_edit' => $user->can('update', $task),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('projects/index', [
            'kpis' => [
                'total_projects' => (clone $projectQuery)->count(),
                'active_projects' => (clone $projectQuery)
                    ->where('status', Project::STATUS_ACTIVE)
                    ->count(),
                'at_risk_projects' => (clone $projectQuery)
                    ->where('health_status', Project::HEALTH_STATUS_AT_RISK)
                    ->count(),
                'overdue_projects' => (clone $projectQuery)
                    ->whereIn('status', [
                        Project::STATUS_DRAFT,
                        Project::STATUS_ACTIVE,
                        Project::STATUS_ON_HOLD,
                    ])
                    ->whereDate('target_end_date', '<', $today)
                    ->count(),
                'tasks_due_7d' => $canViewTasks
                    ? (clone $taskQuery)
                        ->whereIn('status', [
                            ProjectTask::STATUS_DRAFT,
                            ProjectTask::STATUS_TODO,
                            ProjectTask::STATUS_IN_PROGRESS,
                            ProjectTask::STATUS_BLOCKED,
                            ProjectTask::STATUS_REVIEW,
                        ])
                        ->whereBetween('due_date', [$today, $dueWindowEnd])
                        ->count()
                    : 0,
                'unassigned_tasks' => $canViewTasks
                    ? (clone $taskQuery)
                        ->whereNull('assigned_to')
                        ->whereNotIn('status', [
                            ProjectTask::STATUS_DONE,
                            ProjectTask::STATUS_CANCELLED,
                        ])
                        ->count()
                    : 0,
            ],
            'recentProjects' => $recentProjects,
            'recentTasks' => $recentTasks,
            'abilities' => [
                'can_create_project' => $user->can('create', Project::class),
                'can_view_tasks' => $canViewTasks,
                'can_view_billables' => $user->can('viewAny', ProjectBillable::class),
            ],
        ]);
    }
}
