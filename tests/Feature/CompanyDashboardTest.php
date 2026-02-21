<?php

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('company dashboard returns real kpi and activity payload', function () {
    [$member, $company] = makeActiveCompanyMember();

    $teammate = User::factory()->create();
    $company->users()->attach($teammate->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);
    $teammate->forceFill(['current_company_id' => $company->id])->save();

    Invite::create([
        'email' => 'pending@example.com',
        'name' => 'Pending Invite',
        'token' => Str::random(40),
        'role' => 'company_member',
        'company_id' => $company->id,
        'created_by' => $member->id,
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_PENDING,
    ]);

    Invite::create([
        'email' => 'accepted@example.com',
        'name' => 'Accepted Invite',
        'token' => Str::random(40),
        'role' => 'company_member',
        'company_id' => $company->id,
        'created_by' => $member->id,
        'expires_at' => now()->addDays(7),
        'accepted_at' => now()->subDay(),
        'delivery_status' => Invite::DELIVERY_SENT,
    ]);

    Invite::create([
        'email' => 'expired@example.com',
        'name' => 'Expired Invite',
        'token' => Str::random(40),
        'role' => 'company_member',
        'company_id' => $company->id,
        'created_by' => $member->id,
        'expires_at' => now()->subDay(),
        'delivery_status' => Invite::DELIVERY_FAILED,
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $member->id,
        'auditable_type' => User::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'created',
        'changes' => ['after' => ['name' => 'Record A']],
        'metadata' => ['source' => 'tests'],
        'created_at' => now()->subDay(),
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $member->id,
        'auditable_type' => User::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'updated',
        'changes' => ['after' => ['name' => 'Record B']],
        'metadata' => ['source' => 'tests'],
        'created_at' => now()->subDays(2),
    ]);

    actingAs($member)
        ->get(route('company.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/dashboard')
            ->where('companySummary.name', $company->name)
            ->where('kpis.team_members', 2)
            ->where('kpis.pending_invites', 1)
            ->where('kpis.activity_events_7d', 2)
            ->where('inviteStatusMix.pending', 1)
            ->where('inviteStatusMix.accepted', 1)
            ->where('inviteStatusMix.expired', 1)
            ->has('activityTrend', 14)
            ->has('masterDataBreakdown', 8)
            ->has('recentActivity')
        );
});

