<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectMilestone;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function projectsMilestoneAssignRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

function projectsMilestoneCurrency(string $companyId, string $userId): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => 'M'.Str::upper(Str::random(2)),
        'name' => 'Project Milestone Currency '.Str::upper(Str::random(4)),
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function projectsMilestoneProject(
    string $companyId,
    User $manager,
    Currency $currency,
    ?User $member = null,
): Project {
    $project = Project::create([
        'company_id' => $companyId,
        'currency_id' => $currency->id,
        'project_code' => 'PRJ-MS-'.Str::upper(Str::random(4)),
        'name' => 'Project Milestone '.Str::upper(Str::random(4)),
        'status' => Project::STATUS_ACTIVE,
        'billing_type' => Project::BILLING_TYPE_FIXED_FEE,
        'project_manager_id' => $manager->id,
        'start_date' => now()->toDateString(),
        'target_end_date' => now()->addWeeks(3)->toDateString(),
        'health_status' => Project::HEALTH_STATUS_ON_TRACK,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectMember::create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'project_role' => ProjectMember::ROLE_MANAGER,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    if ($member) {
        ProjectMember::create([
            'company_id' => $companyId,
            'project_id' => $project->id,
            'user_id' => $member->id,
            'project_role' => ProjectMember::ROLE_MEMBER,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);
    }

    return $project;
}

test('project manager can create update and delete milestones', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    projectsMilestoneAssignRole($manager, $company->id, 'project_manager');

    $currency = projectsMilestoneCurrency($company->id, $manager->id);
    $project = projectsMilestoneProject($company->id, $manager, $currency);

    actingAs($manager)
        ->get(route('company.projects.milestones.create', $project))
        ->assertOk();

    actingAs($manager)
        ->post(route('company.projects.milestones.store', $project), [
            'name' => 'Kickoff complete',
            'description' => 'Formal kickoff and plan approval.',
            'sequence' => 1,
            'status' => ProjectMilestone::STATUS_READY_FOR_REVIEW,
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1500,
        ])
        ->assertRedirect();

    $milestone = ProjectMilestone::query()->latest('created_at')->first();

    expect($milestone)->not->toBeNull();
    expect($milestone?->invoice_status)->toBe(ProjectMilestone::INVOICE_STATUS_NOT_READY);
    expect($milestone?->completed_at)->not->toBeNull();

    actingAs($manager)
        ->put(route('company.projects.milestones.update', $milestone), [
            'name' => 'Kickoff approved',
            'description' => 'Formal kickoff, approved by delivery lead.',
            'sequence' => 1,
            'status' => ProjectMilestone::STATUS_APPROVED,
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1750,
        ])
        ->assertRedirect(route('company.projects.milestones.edit', $milestone));

    $milestone = $milestone->fresh();

    expect($milestone?->status)->toBe(ProjectMilestone::STATUS_APPROVED);
    expect($milestone?->invoice_status)->toBe(ProjectMilestone::INVOICE_STATUS_READY);
    expect($milestone?->approved_by)->toBe($manager->id);
    expect((float) $milestone?->amount)->toBe(1750.0);

    $billable = ProjectBillable::query()
        ->where('source_type', ProjectMilestone::class)
        ->where('source_id', $milestone?->id)
        ->first();

    expect($billable)->not->toBeNull();
    expect($billable?->billable_type)->toBe(ProjectBillable::TYPE_MILESTONE);
    expect((float) $billable?->quantity)->toBe(1.0);
    expect((float) $billable?->amount)->toBe(1750.0);
    expect($billable?->status)->toBe(ProjectBillable::STATUS_READY);
    expect((float) $project->fresh()?->actual_billable_amount)->toBe(1750.0);

    actingAs($manager)
        ->put(route('company.projects.milestones.update', $milestone), [
            'name' => 'Kickoff paused',
            'description' => 'Approval rolled back pending change request.',
            'sequence' => 1,
            'status' => ProjectMilestone::STATUS_IN_PROGRESS,
            'due_date' => now()->addDays(10)->toDateString(),
            'amount' => 1750,
        ])
        ->assertRedirect(route('company.projects.milestones.edit', $milestone));

    expect(
        ProjectBillable::query()
            ->where('source_type', ProjectMilestone::class)
            ->where('source_id', $milestone?->id)
            ->value('status')
    )->toBe(ProjectBillable::STATUS_CANCELLED);
    expect((float) $project->fresh()?->actual_billable_amount)->toBe(0.0);

    actingAs($manager)
        ->delete(route('company.projects.milestones.destroy', $milestone))
        ->assertRedirect(route('company.projects.show', $project));

    $this->assertSoftDeleted('project_milestones', [
        'id' => $milestone?->id,
    ]);
});

test('project user cannot create milestones for the project', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    $member = User::factory()->create();

    $company->users()->attach($member->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    projectsMilestoneAssignRole($manager, $company->id, 'project_manager');
    projectsMilestoneAssignRole($member, $company->id, 'project_user');

    $currency = projectsMilestoneCurrency($company->id, $manager->id);
    $project = projectsMilestoneProject($company->id, $manager, $currency, $member);

    actingAs($member)
        ->get(route('company.projects.show', $project))
        ->assertOk();

    actingAs($member)
        ->get(route('company.projects.milestones.create', $project))
        ->assertForbidden();

    actingAs($member)
        ->post(route('company.projects.milestones.store', $project), [
            'name' => 'Unauthorized milestone',
            'status' => ProjectMilestone::STATUS_DRAFT,
        ])
        ->assertForbidden();
});
