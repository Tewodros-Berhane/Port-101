<?php

use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Reports\CompanyReportingSettingsService;
use App\Modules\Reports\CompanyReportsService;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignReportsRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Reports Role '.Str::upper(Str::random(4)),
        'slug' => 'reports-role-'.Str::lower(Str::random(8)),
        'description' => 'Reports workflow role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'reports']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $user->memberships()->updateOrCreate(
        ['company_id' => $companyId],
        [
            'role_id' => $role->id,
            'is_owner' => false,
        ],
    );

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('company reports page and exports are available for report-enabled users', function () {
    [$reportUser, $company] = makeActiveCompanyMember();

    assignReportsRole($reportUser, $company->id, [
        'reports.view',
        'reports.export',
    ]);

    actingAs($reportUser)
        ->get(route('company.modules.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->has('reportCatalog'));

    actingAs($reportUser)
        ->get(route('company.reports.export', [
            'reportKey' => CompanyReportsService::REPORT_APPROVAL_GOVERNANCE,
            'format' => 'pdf',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    actingAs($reportUser)
        ->get(route('company.reports.export', [
            'reportKey' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
            'format' => 'xlsx',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('company report presets and delivery schedule persist through settings service', function () {
    [$reportUser, $company] = makeActiveCompanyMember();

    assignReportsRole($reportUser, $company->id, [
        'reports.view',
        'reports.export',
    ]);

    actingAs($reportUser)
        ->post(route('company.reports.presets.store'), [
            'name' => 'Weekly approvals',
            'trend_window' => 30,
            'approval_status' => 'pending',
        ])
        ->assertRedirect(route('company.modules.reports'));

    /** @var CompanyReportingSettingsService $settings */
    $settings = app(CompanyReportingSettingsService::class);

    $preset = collect($settings->getPresets($company->id))->first();

    expect($preset)->not->toBeNull();

    actingAs($reportUser)
        ->put(route('company.reports.delivery-schedule.update'), [
            'enabled' => true,
            'preset_id' => $preset['id'],
            'report_key' => CompanyReportsService::REPORT_APPROVAL_GOVERNANCE,
            'format' => 'xlsx',
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '09:00',
            'timezone' => 'UTC',
        ])
        ->assertRedirect(route('company.modules.reports'));

    $schedule = $settings->getDeliverySchedule($company->id);

    expect($schedule['enabled'])->toBeTrue();
    expect($schedule['preset_id'])->toBe($preset['id']);
    expect($schedule['report_key'])->toBe(CompanyReportsService::REPORT_APPROVAL_GOVERNANCE);
    expect($schedule['format'])->toBe('xlsx');
});
