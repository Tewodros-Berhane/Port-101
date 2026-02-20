<?php

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\Notifications\NotificationGovernanceService;
use App\Core\Platform\DashboardPreferencesService;
use App\Core\Platform\OperationsReportingSettingsService;
use App\Core\Settings\Models\Setting;
use App\Models\User;
use App\Notifications\InviteAcceptedNotification;
use App\Notifications\InviteDeliveryFailedNotification;
use App\Notifications\NotificationGovernanceEscalationNotification;
use App\Notifications\PlatformNotificationDigestNotification;
use App\Notifications\PlatformOperationsReportDeliveryNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

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
        ->assertRedirect(route('platform.governance'));

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

test('superadmin can save and delete operations report presets and update schedule', function () {
    $superAdmin = createSuperAdmin();

    actingAs($superAdmin)
        ->post(route('platform.dashboard.report-presets.store'), [
            'name' => 'Weekly operations',
            'trend_window' => 30,
            'admin_action' => 'updated',
            'admin_actor_id' => '',
            'admin_start_date' => '',
            'admin_end_date' => '',
        ])
        ->assertRedirect(route('platform.dashboard'));

    $presetsSetting = Setting::query()
        ->where('key', OperationsReportingSettingsService::PRESETS_KEY)
        ->first();

    $presetId = $presetsSetting?->value[0]['id'] ?? null;

    expect($presetId)->not->toBeNull();

    actingAs($superAdmin)
        ->put(route('platform.dashboard.report-delivery-schedule.update'), [
            'enabled' => true,
            'preset_id' => $presetId,
            'format' => 'csv',
            'frequency' => 'weekly',
            'day_of_week' => 2,
            'time' => '08:30',
            'timezone' => 'UTC',
        ])
        ->assertRedirect(route('platform.governance'));

    $schedule = Setting::query()
        ->where('key', OperationsReportingSettingsService::DELIVERY_SCHEDULE_KEY)
        ->first();

    expect($schedule?->value['enabled'])->toBeTrue();
    expect($schedule?->value['preset_id'])->toBe($presetId);

    actingAs($superAdmin)
        ->delete(route('platform.dashboard.report-presets.destroy', $presetId))
        ->assertRedirect(route('platform.dashboard'));

    $updatedSchedule = Setting::query()
        ->where('key', OperationsReportingSettingsService::DELIVERY_SCHEDULE_KEY)
        ->first();

    expect($updatedSchedule?->value['preset_id'] ?? null)->toBeNull();
});

test('scheduled operations report delivery command sends notifications', function () {
    $superAdmin = createSuperAdmin();
    $actor = createSuperAdmin();
    $settings = app(OperationsReportingSettingsService::class);

    $company = Company::create([
        'name' => 'Scheduled Ops Co',
        'slug' => 'scheduled-ops-co-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $superAdmin->id,
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $actor->id,
        'auditable_type' => User::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'updated',
        'changes' => ['after' => ['name' => 'Ops']],
        'metadata' => ['source' => 'test'],
        'created_at' => now()->subHour(),
    ]);

    Invite::create([
        'email' => 'scheduled@example.com',
        'name' => 'Scheduled Invite',
        'token' => Str::random(40),
        'role' => 'company_member',
        'company_id' => $company->id,
        'created_by' => $superAdmin->id,
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_SENT,
        'delivery_attempts' => 1,
    ]);

    $preset = $settings->savePreset('Scheduled preset', [
        'trend_window' => 30,
        'admin_action' => 'updated',
        'admin_actor_id' => $actor->id,
    ]);

    $settings->setDeliverySchedule([
        'enabled' => true,
        'preset_id' => $preset['id'],
        'format' => 'csv',
        'frequency' => 'weekly',
        'day_of_week' => 1,
        'time' => '08:00',
        'timezone' => 'UTC',
    ]);

    $this->artisan('platform:operations-reports:deliver-scheduled', ['--force' => true])
        ->assertSuccessful();

    expect(
        $superAdmin->fresh()
            ->notifications()
            ->where('type', PlatformOperationsReportDeliveryNotification::class)
            ->count()
    )->toBe(1);

    $updatedSchedule = $settings->getDeliverySchedule();
    expect($updatedSchedule['last_sent_at'])->not->toBeNull();
});

test('platform dashboard returns governance analytics payload', function () {
    $superAdmin = createSuperAdmin();

    $superAdmin->notify(new NotificationGovernanceEscalationNotification(
        eventName: 'Invite delivery failed',
        severity: 'critical',
        source: 'tests',
        escalationDelayMinutes: 15
    ));

    $digest = new PlatformNotificationDigestNotification(
        periodLabel: 'daily digest',
        totalNotifications: 12,
        severityCounts: [
            'low' => 5,
            'medium' => 4,
            'high' => 2,
            'critical' => 1,
        ]
    );

    $superAdmin->notify($digest);

    $superAdmin->notify(new InviteDeliveryFailedNotification(
        inviteEmail: 'noisy@example.com',
        contextLabel: 'Platform',
        errorMessage: 'SMTP timeout',
        isPlatformInvite: true
    ));
    $superAdmin->notify(new InviteDeliveryFailedNotification(
        inviteEmail: 'noisy2@example.com',
        contextLabel: 'Platform',
        errorMessage: 'SMTP timeout',
        isPlatformInvite: true
    ));

    actingAs($superAdmin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notificationGovernanceAnalytics.escalations')
            ->has('notificationGovernanceAnalytics.digest_coverage')
            ->has('notificationGovernanceAnalytics.noisy_events')
            ->where('notificationGovernanceAnalytics.digest_coverage.sent', 1)
        );
});

test('platform governance page returns governance settings payload', function () {
    $superAdmin = createSuperAdmin();

    actingAs($superAdmin)
        ->get(route('platform.governance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/governance')
            ->has('analyticsFilters.trend_window')
            ->has('notificationGovernance')
            ->has('operationsReportDeliverySchedule')
            ->has('operationsReportPresets')
        );
});

test('superadmin can persist dashboard preferences and default filters', function () {
    $superAdmin = createSuperAdmin();
    $operationsSettings = app(OperationsReportingSettingsService::class);
    $preset = $operationsSettings->savePreset('Default 90d', [
        'trend_window' => 90,
        'admin_action' => null,
        'admin_actor_id' => null,
        'admin_start_date' => null,
        'admin_end_date' => null,
        'invite_delivery_status' => Invite::DELIVERY_FAILED,
    ], $superAdmin->id);

    actingAs($superAdmin)
        ->put(route('platform.dashboard.preferences.update'), [
            'default_preset_id' => $preset['id'],
            'default_operations_tab' => 'admin_actions',
            'layout' => 'analytics_first',
            'hidden_widgets' => [
                'operations_presets',
            ],
        ])
        ->assertRedirect(route('platform.dashboard', [
            'operations_tab' => 'admin_actions',
        ]));

    $stored = Setting::query()
        ->where('key', DashboardPreferencesService::KEY)
        ->where('user_id', $superAdmin->id)
        ->first();

    expect($stored?->value['default_preset_id'] ?? null)->toBe($preset['id']);
    expect($stored?->value['layout'] ?? null)->toBe('analytics_first');

    actingAs($superAdmin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('operationsFilters.trend_window', 90)
            ->where('operationsFilters.invite_delivery_status', Invite::DELIVERY_FAILED)
            ->where('operationsTab', 'admin_actions')
            ->where('dashboardPreferences.layout', 'analytics_first')
        );
});

test('platform dashboard supports invite drill-down tab and delivery status filter', function () {
    $superAdmin = createSuperAdmin();
    $company = Company::create([
        'name' => 'Invite Drilldown Co',
        'slug' => 'invite-drilldown-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $superAdmin->id,
    ]);

    Invite::create([
        'email' => 'failed@example.com',
        'name' => 'Failed Invite',
        'token' => Str::random(40),
        'role' => 'company_member',
        'company_id' => $company->id,
        'created_by' => $superAdmin->id,
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_FAILED,
        'delivery_attempts' => 1,
    ]);

    Invite::create([
        'email' => 'sent@example.com',
        'name' => 'Sent Invite',
        'token' => Str::random(40),
        'role' => 'company_member',
        'company_id' => $company->id,
        'created_by' => $superAdmin->id,
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_SENT,
        'delivery_attempts' => 1,
    ]);

    actingAs($superAdmin)
        ->get(route('platform.dashboard', [
            'operations_tab' => 'invites',
            'invite_delivery_status' => Invite::DELIVERY_FAILED,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('operationsTab', 'invites')
            ->where('operationsFilters.invite_delivery_status', Invite::DELIVERY_FAILED)
            ->has('recentInvites', 1)
            ->where('recentInvites.0.email', 'failed@example.com')
            ->where('recentInvites.0.delivery_status', Invite::DELIVERY_FAILED)
        );
});
