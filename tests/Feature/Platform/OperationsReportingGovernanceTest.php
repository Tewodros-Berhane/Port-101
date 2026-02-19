<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Settings\Models\Setting;
use App\Models\User;
use App\Notifications\InviteAcceptedNotification;
use App\Notifications\InviteDeliveryFailedNotification;
use App\Notifications\NotificationGovernanceEscalationNotification;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

function createSuperAdmin(): User
{
    return User::factory()->create([
        'is_super_admin' => true,
    ]);
}

test('superadmin can export filtered admin actions report', function () {
    $superAdmin = createSuperAdmin();
    $actor = createSuperAdmin();
    $otherActor = User::factory()->create(['is_super_admin' => false]);
    $company = Company::create([
        'name' => 'Ops Export Co',
        'slug' => 'ops-export-co-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $superAdmin->id,
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $actor->id,
        'auditable_type' => User::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'created',
        'changes' => ['after' => ['name' => 'A']],
        'metadata' => ['source' => 'test'],
        'created_at' => now()->subMinutes(5),
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $actor->id,
        'auditable_type' => User::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'updated',
        'changes' => ['after' => ['name' => 'B']],
        'metadata' => ['source' => 'test'],
        'created_at' => now()->subMinutes(2),
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $otherActor->id,
        'auditable_type' => User::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'updated',
        'changes' => ['after' => ['name' => 'C']],
        'metadata' => ['source' => 'test'],
        'created_at' => now()->subMinute(),
    ]);

    $response = actingAs($superAdmin)
        ->get(route('platform.dashboard.export.admin-actions', [
            'admin_action' => 'updated',
            'admin_actor_id' => $actor->id,
            'format' => 'csv',
        ]));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain('"Created At",Action,"Record Type","Record ID",Company,Actor');
    expect($content)->toContain(',updated,');
    expect($content)->not->toContain(',created,');
});

test('superadmin can update notification governance and severity rules are enforced', function () {
    $superAdmin = createSuperAdmin();
    $recipient = User::factory()->create();

    actingAs($superAdmin)
        ->put(route('platform.dashboard.notification-governance.update'), [
            'min_severity' => 'high',
            'escalation_enabled' => true,
            'escalation_severity' => 'high',
            'escalation_delay_minutes' => 15,
            'digest_enabled' => true,
            'digest_frequency' => 'weekly',
            'digest_day_of_week' => 3,
            'digest_time' => '09:30',
            'digest_timezone' => 'UTC',
        ])
        ->assertRedirect(route('platform.dashboard'));

    $stored = Setting::query()
        ->where('key', 'platform.notifications.min_severity')
        ->first();

    expect($stored?->value)->toBe('high');

    $governance = app(NotificationGovernanceService::class);

    $governance->notify(
        recipients: [$recipient],
        notification: new InviteAcceptedNotification(
            inviteeEmail: 'new.user@example.com',
            companyName: 'Acme',
            acceptedBy: 'Owner'
        ),
        severity: 'low',
        context: [
            'event' => 'Invite accepted',
            'source' => 'tests',
        ]
    );

    expect($recipient->fresh()->notifications()->count())->toBe(0);

    $governance->notify(
        recipients: [$recipient],
        notification: new InviteDeliveryFailedNotification(
            inviteEmail: 'failed.user@example.com',
            contextLabel: 'Acme',
            errorMessage: 'SMTP timeout',
            isPlatformInvite: true
        ),
        severity: 'critical',
        context: [
            'event' => 'Invite delivery failed',
            'source' => 'tests',
        ]
    );

    expect($recipient->fresh()->notifications()->count())->toBe(1);
    expect(
        $superAdmin->fresh()
            ->notifications()
            ->where('type', NotificationGovernanceEscalationNotification::class)
            ->count()
    )->toBe(1);
});

test('superadmin can export delivery trend report', function () {
    $superAdmin = createSuperAdmin();

    $response = actingAs($superAdmin)
        ->get(route('platform.dashboard.export.delivery-trends', [
            'trend_window' => 30,
            'format' => 'json',
        ]));

    $response->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'window_days',
                'sent',
                'failed',
                'pending',
                'total',
                'failure_rate',
            ],
            'rows',
        ]);
});
