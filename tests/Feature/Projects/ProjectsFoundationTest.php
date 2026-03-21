<?php

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectStage;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('projects foundation tables exist and models persist core relationships', function () {
    foreach ([
        'projects',
        'project_members',
        'project_stages',
        'project_tasks',
        'project_timesheets',
        'project_milestones',
        'project_billables',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    [$user, $company] = makeActiveCompanyMember();

    $customer = Partner::create([
        'company_id' => $company->id,
        'code' => 'CUST-'.Str::upper(Str::random(4)),
        'name' => 'Project Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $currency = Currency::create([
        'company_id' => $company->id,
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = SalesOrder::create([
        'company_id' => $company->id,
        'quote_id' => null,
        'partner_id' => $customer->id,
        'order_number' => 'SO-PROJ-'.Str::upper(Str::random(4)),
        'status' => SalesOrder::STATUS_CONFIRMED,
        'order_date' => now()->toDateString(),
        'subtotal' => 1500,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 1500,
        'requires_approval' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $project = Project::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'sales_order_id' => $order->id,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-'.Str::upper(Str::random(4)),
        'name' => 'Implementation Project',
        'description' => 'Initial project foundation test record.',
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_TIME_AND_MATERIAL,
        'project_manager_id' => $user->id,
        'start_date' => now()->toDateString(),
        'budget_amount' => 5000,
        'budget_hours' => 120,
        'actual_cost_amount' => 250,
        'actual_billable_amount' => 600,
        'progress_percent' => 12.5,
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $member = ProjectMember::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'user_id' => $user->id,
        'project_role' => ProjectMember::ROLE_MANAGER,
        'allocation_percent' => 100,
        'hourly_cost_rate' => 40,
        'hourly_bill_rate' => 85,
        'is_billable_by_default' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $stage = ProjectStage::create([
        'company_id' => $company->id,
        'name' => 'In Progress',
        'sequence' => 2,
        'color' => 'blue',
        'is_closed_stage' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $task = ProjectTask::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'customer_id' => $customer->id,
        'task_number' => 'TSK-'.Str::upper(Str::random(4)),
        'title' => 'Kickoff workshop',
        'description' => 'Run project kickoff and gather requirements.',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'priority' => ProjectTask::PRIORITY_HIGH,
        'assigned_to' => $user->id,
        'start_date' => now()->toDateString(),
        'due_date' => now()->addWeek()->toDateString(),
        'estimated_hours' => 8,
        'actual_hours' => 2.5,
        'is_billable' => true,
        'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $timesheet = ProjectTimesheet::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'task_id' => $task->id,
        'user_id' => $user->id,
        'work_date' => now()->toDateString(),
        'description' => 'Kickoff preparation',
        'hours' => 2.5,
        'is_billable' => true,
        'cost_rate' => 40,
        'bill_rate' => 85,
        'cost_amount' => 100,
        'billable_amount' => 212.5,
        'approval_status' => ProjectTimesheet::APPROVAL_STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now(),
        'invoice_status' => ProjectTimesheet::INVOICE_STATUS_READY,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $milestone = ProjectMilestone::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'name' => 'Discovery complete',
        'description' => 'Requirements and process map approved.',
        'sequence' => 1,
        'status' => ProjectMilestone::STATUS_READY_FOR_REVIEW,
        'due_date' => now()->addWeeks(2)->toDateString(),
        'amount' => 1200,
        'invoice_status' => ProjectMilestone::INVOICE_STATUS_NOT_READY,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $billable = ProjectBillable::create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'billable_type' => ProjectBillable::TYPE_TIMESHEET,
        'source_type' => ProjectTimesheet::class,
        'source_id' => $timesheet->id,
        'customer_id' => $customer->id,
        'description' => 'Kickoff preparation time',
        'quantity' => 2.5,
        'unit_price' => 85,
        'amount' => 212.5,
        'currency_id' => $currency->id,
        'status' => ProjectBillable::STATUS_READY,
        'approval_status' => ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($project->customer?->is($customer))->toBeTrue();
    expect($project->salesOrder?->is($order))->toBeTrue();
    expect($project->projectManager?->is($user))->toBeTrue();
    expect($project->members()->count())->toBe(1);
    expect($project->tasks()->count())->toBe(1);
    expect($project->timesheets()->count())->toBe(1);
    expect($project->milestones()->count())->toBe(1);
    expect($project->billables()->count())->toBe(1);
    expect($member->project?->is($project))->toBeTrue();
    expect($task->stage?->is($stage))->toBeTrue();
    expect($task->assignee?->is($user))->toBeTrue();
    expect($timesheet->task?->is($task))->toBeTrue();
    expect($timesheet->billables()->count())->toBe(1);
    expect($milestone->project?->is($project))->toBeTrue();
    expect($billable->source?->is($timesheet))->toBeTrue();
    expect((string) $billable->project_id)->toBe((string) $project->id);
});
