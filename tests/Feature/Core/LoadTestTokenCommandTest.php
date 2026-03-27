<?php

use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

test('load test token command issues a token for an active company user and switches current company', function () {
    $user = User::factory()->create([
        'email' => 'load-owner@port101.test',
    ]);

    $company = Company::create([
        'name' => 'Load Test Company',
        'slug' => 'load-test-company',
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $user->id,
    ]);

    $role = Role::create([
        'name' => 'Load Test Role',
        'slug' => 'load-test-role',
    ]);

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_owner' => true,
    ]);

    $exitCode = Artisan::call('ops:load-test:token', [
        'email' => $user->email,
        '--company' => $company->slug,
        '--name' => 'ops-load-test',
        '--json' => true,
    ]);

    $output = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($output['user_email'] ?? null)->toBe('load-owner@port101.test')
        ->and($output['company_slug'] ?? null)->toBe('load-test-company')
        ->and($output['token_name'] ?? null)->toBe('ops-load-test')
        ->and($output['token'] ?? null)->not->toBeEmpty();

    expect($user->fresh()?->current_company_id)->toBe($company->id)
        ->and(PersonalAccessToken::query()->where('tokenable_id', $user->id)->where('name', 'ops-load-test')->exists())->toBeTrue();
});

test('load test token command fails when the requested company does not exist', function () {
    $exitCode = Artisan::call('ops:load-test:token', [
        '--company' => 'missing-company',
        '--json' => true,
    ]);

    $output = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($output['message'] ?? null)->toBe('Load-test token issuance failed.')
        ->and($output['errors']['company'][0] ?? null)->toBe('Active company [missing-company] was not found.');
});
