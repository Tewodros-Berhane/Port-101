<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\Settings\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\artisan;

test('audit log prune command uses company retention settings', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $companyName = 'Retention Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);

    Setting::create([
        'company_id' => $company->id,
        'user_id' => null,
        'key' => 'core.audit_logs.retention_days',
        'value' => 30,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    $oldLog = AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $actor->id,
        'auditable_type' => Company::class,
        'auditable_id' => $company->id,
        'action' => 'updated',
        'changes' => ['before' => ['name' => 'Old'], 'after' => ['name' => 'New']],
        'metadata' => ['source' => 'test'],
        'created_at' => now()->subDays(45),
    ]);

    $recentLog = AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $actor->id,
        'auditable_type' => Company::class,
        'auditable_id' => $company->id,
        'action' => 'updated',
        'changes' => ['before' => ['name' => 'Old'], 'after' => ['name' => 'Newer']],
        'metadata' => ['source' => 'test'],
        'created_at' => now()->subDays(10),
    ]);

    artisan('core:audit-logs:prune')
        ->assertSuccessful();

    expect(AuditLog::withoutGlobalScopes()->whereKey($oldLog->id)->exists())->toBeFalse();
    expect(AuditLog::withoutGlobalScopes()->whereKey($recentLog->id)->exists())->toBeTrue();
});

