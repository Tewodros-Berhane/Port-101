<?php

use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Notifications\CompanySettingsUpdatedNotification;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

function createCompanyUserForNotifications(array $permissions): User
{
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $companyName = 'Notification Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);

    $role = Role::create([
        'name' => 'Notification Role '.Str::upper(Str::random(4)),
        'slug' => 'notification-role-'.Str::lower(Str::random(8)),
        'description' => 'Notification test role',
        'company_id' => null,
    ]);

    $permissionIds = collect($permissions)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'core']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill(['current_company_id' => $company->id])->save();

    return $user;
}

test('user can view mark read and delete in-app notifications', function () {
    $user = createCompanyUserForNotifications([
        'core.notifications.view',
        'core.notifications.manage',
    ]);

    $user->notify(new CompanySettingsUpdatedNotification(
        companyName: 'Acme Corp',
        updatedBy: 'Admin User'
    ));

    $user->notify(new CompanySettingsUpdatedNotification(
        companyName: 'Acme Corp',
        updatedBy: 'Owner User'
    ));

    $notificationId = $user->notifications()->latest('created_at')->first()->id;

    actingAs($user)
        ->get(route('core.notifications.index'))
        ->assertOk()
        ->assertSee('Notifications');

    actingAs($user)
        ->post(route('core.notifications.mark-read', ['notificationId' => $notificationId]))
        ->assertStatus(303);

    expect($user->fresh()->unreadNotifications()->count())->toBe(1);

    actingAs($user)
        ->post(route('core.notifications.mark-all-read'))
        ->assertStatus(303);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);

    actingAs($user)
        ->delete(route('core.notifications.destroy', ['notificationId' => $notificationId]))
        ->assertStatus(303);

    expect(
        $user->fresh()->notifications()->where('id', $notificationId)->exists()
    )->toBeFalse();
});

