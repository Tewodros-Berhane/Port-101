<?php

use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Reports\CompanyReportsService;
use App\Modules\Reports\Models\ReportExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function assignReportsApiRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Reports API Role '.Str::upper(Str::random(4)),
        'slug' => 'reports-api-role-'.Str::lower(Str::random(8)),
        'description' => 'Reports API test role',
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

test('api v1 reports exports can be created polled and downloaded', function () {
    Storage::fake('local');

    [$reportUser, $company] = makeActiveCompanyMember();
    [$otherUser, $otherCompany] = makeActiveCompanyMember();

    assignReportsApiRole($reportUser, $company->id, [
        'reports.view',
        'reports.export',
    ]);
    assignReportsApiRole($otherUser, $otherCompany->id, [
        'reports.view',
        'reports.export',
    ]);

    $otherExport = ReportExport::create([
        'company_id' => $otherCompany->id,
        'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
        'report_title' => 'Other Company Export',
        'format' => ReportExport::FORMAT_PDF,
        'status' => ReportExport::STATUS_COMPLETED,
        'filters' => ['trend_window' => 30],
        'requested_by_user_id' => $otherUser->id,
        'disk' => 'local',
        'file_path' => 'report-exports/'.$otherCompany->id.'/other-company-export.pdf',
        'file_name' => 'other-company-export.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 10,
        'row_count' => 2,
        'completed_at' => now(),
        'created_by' => $otherUser->id,
        'updated_by' => $otherUser->id,
    ]);

    Storage::disk('local')->put($otherExport->file_path, 'other-pdf');

    Sanctum::actingAs($reportUser);

    $createResponse = postJson('/api/v1/reports/exports', [
        'report_key' => CompanyReportsService::REPORT_APPROVAL_GOVERNANCE,
        'format' => ReportExport::FORMAT_PDF,
        'trend_window' => 30,
        'approval_status' => 'pending',
    ], apiIdempotencyHeaders())
        ->assertAccepted()
        ->assertJsonPath('data.report_key', CompanyReportsService::REPORT_APPROVAL_GOVERNANCE)
        ->assertJsonPath('data.format', ReportExport::FORMAT_PDF)
        ->assertJsonPath('data.status', ReportExport::STATUS_COMPLETED)
        ->assertJsonPath('data.filters.approval_status', 'pending');

    $exportId = (string) $createResponse->json('data.id');

    $export = ReportExport::query()->findOrFail($exportId);

    expect($export->status)->toBe(ReportExport::STATUS_COMPLETED);
    expect($export->file_name)->not->toBeNull();
    expect(str_ends_with((string) $export->file_name, '.pdf'))->toBeTrue();
    expect($export->file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists((string) $export->file_path))->toBeTrue();

    getJson('/api/v1/reports/exports/'.$exportId)
        ->assertOk()
        ->assertJsonPath('data.id', $exportId)
        ->assertJsonPath('data.status', ReportExport::STATUS_COMPLETED)
        ->assertJsonPath('data.can_download', true)
        ->assertJsonPath('data.download_url', '/api/v1/reports/exports/'.$exportId.'/download');

    getJson('/api/v1/reports/exports/'.$otherExport->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    get('/api/v1/reports/exports/'.$exportId.'/download')
        ->assertOk()
        ->assertHeader('X-API-Version', 'v1')
        ->assertHeader('content-type', 'application/pdf');
});

test('api v1 reports exports use shared permission validation and pending-download error contracts', function () {
    Storage::fake('local');

    [$reportUser, $company] = makeActiveCompanyMember();

    assignReportsApiRole($reportUser, $company->id, [
        'reports.view',
    ]);

    Sanctum::actingAs($reportUser);

    postJson('/api/v1/reports/exports', [
        'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
        'format' => ReportExport::FORMAT_XLSX,
        'trend_window' => 7,
    ], apiIdempotencyHeaders())
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    assignReportsApiRole($reportUser, $company->id, [
        'reports.view',
        'reports.export',
    ]);

    Sanctum::actingAs($reportUser);

    postJson('/api/v1/reports/exports', [
        'report_key' => 'not-a-real-report',
        'format' => 'csv',
    ], apiIdempotencyHeaders())
        ->assertUnprocessable()
        ->assertJsonStructure([
            'message',
            'errors' => ['report_key', 'format'],
        ]);

    $pendingExport = ReportExport::create([
        'company_id' => $company->id,
        'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
        'format' => ReportExport::FORMAT_XLSX,
        'status' => ReportExport::STATUS_PENDING,
        'filters' => ['trend_window' => 7],
        'requested_by_user_id' => $reportUser->id,
        'created_by' => $reportUser->id,
        'updated_by' => $reportUser->id,
    ]);

    getJson('/api/v1/reports/exports/'.$pendingExport->id)
        ->assertOk()
        ->assertJsonPath('data.status', ReportExport::STATUS_PENDING)
        ->assertJsonPath('data.can_download', false);

    getJson('/api/v1/reports/exports/'.$pendingExport->id.'/download')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Report export is not ready for download.');
});

test('api v1 report export creation replays duplicate idempotent requests', function () {
    Storage::fake('local');

    [$reportUser, $company] = makeActiveCompanyMember();

    assignReportsApiRole($reportUser, $company->id, [
        'reports.view',
        'reports.export',
    ]);

    Sanctum::actingAs($reportUser);

    $payload = [
        'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
        'format' => ReportExport::FORMAT_XLSX,
        'trend_window' => 30,
    ];

    $key = 'reports-export-create';

    postJson('/api/v1/reports/exports', $payload)
        ->assertBadRequest()
        ->assertJsonPath('message', 'Idempotency-Key header is required for this endpoint.');

    $firstResponse = postJson('/api/v1/reports/exports', $payload, apiIdempotencyHeaders($key))
        ->assertAccepted()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Port101-Idempotency-Replayed', 'false');

    $exportId = (string) $firstResponse->json('data.id');

    postJson('/api/v1/reports/exports', $payload, apiIdempotencyHeaders($key))
        ->assertAccepted()
        ->assertHeader('Idempotency-Key', $key)
        ->assertHeader('X-Port101-Idempotency-Replayed', 'true')
        ->assertJsonPath('data.id', $exportId);

    expect(ReportExport::query()->where('company_id', $company->id)->count())->toBe(1);
});

test('api v1 report export creation is throttled', function () {
    Storage::fake('local');

    [$reportUser, $company] = makeActiveCompanyMember();

    assignReportsApiRole($reportUser, $company->id, [
        'reports.view',
        'reports.export',
    ]);

    Sanctum::actingAs($reportUser);

    foreach (range(1, 10) as $attempt) {
        postJson('/api/v1/reports/exports', [
            'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
            'format' => ReportExport::FORMAT_XLSX,
            'trend_window' => 30,
        ], apiIdempotencyHeaders('report-export-throttle-'.$attempt))
            ->assertAccepted();
    }

    postJson('/api/v1/reports/exports', [
        'report_key' => CompanyReportsService::REPORT_FINANCE_SNAPSHOT,
        'format' => ReportExport::FORMAT_XLSX,
        'trend_window' => 30,
    ], apiIdempotencyHeaders('report-export-throttle-11'))
        ->assertTooManyRequests();
});
