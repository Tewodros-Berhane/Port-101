<?php

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Mail\InviteLinkMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function makeCompanyWithCreator(): array
{
    $creator = User::factory()->create();

    $companyName = 'Invite Company '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $creator->id,
    ]);

    return [$company, $creator];
}

function makeCompanyUserWithInvitePermission(bool $canManage): array
{
    [$company, $owner] = makeCompanyWithCreator();
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Invite Role '.Str::upper(Str::random(4)),
        'slug' => 'invite-role-'.Str::lower(Str::random(8)),
        'description' => 'Invite role',
        'company_id' => null,
    ]);

    if ($canManage) {
        $permission = Permission::firstOrCreate(
            ['slug' => 'core.users.manage'],
            ['name' => 'Manage Users', 'group' => 'core']
        );

        $role->permissions()->sync([$permission->id]);
    }

    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    return [$user, $company, $owner];
}

test('invite token acceptance provisions account and company membership', function () {
    [$company, $creator] = makeCompanyWithCreator();

    $memberRole = Role::create([
        'name' => 'Member',
        'slug' => 'member',
        'description' => 'Member role',
        'company_id' => null,
    ]);

    $token = Str::random(40);
    $email = 'invitee.'.Str::lower(Str::random(5)).'@example.com';

    $invite = Invite::create([
        'email' => $email,
        'name' => 'Invited User',
        'role' => 'company_member',
        'company_id' => $company->id,
        'token' => $token,
        'expires_at' => now()->addDays(3),
        'created_by' => $creator->id,
    ]);

    post(route('invites.accept.store', ['token' => $token]), [
        'name' => 'Invited User',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $acceptedUser = User::query()->where('email', $email)->first();

    expect($acceptedUser)->not->toBeNull();
    expect($invite->fresh()->accepted_at)->not->toBeNull();

    expect(
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $acceptedUser->id)
            ->where('role_id', $memberRole->id)
            ->where('is_owner', false)
            ->exists()
    )->toBeTrue();
});

test('invite acceptance page handles invalid and expired tokens', function () {
    [$company, $creator] = makeCompanyWithCreator();

    $expiredToken = Str::random(40);

    Invite::create([
        'email' => 'expired.'.Str::lower(Str::random(5)).'@example.com',
        'name' => 'Expired Invite',
        'role' => 'company_member',
        'company_id' => $company->id,
        'token' => $expiredToken,
        'expires_at' => Carbon::yesterday(),
        'created_by' => $creator->id,
    ]);

    get(route('invites.accept.show', ['token' => 'missing-token']))
        ->assertOk()
        ->assertSee('This invite link is invalid.');

    get(route('invites.accept.show', ['token' => $expiredToken]))
        ->assertOk()
        ->assertSee('This invite has expired.');
});

test('company invites require manage users permission', function () {
    [$user, $company, $creator] = makeCompanyUserWithInvitePermission(false);

    $invite = Invite::create([
        'email' => 'blocked.'.Str::lower(Str::random(5)).'@example.com',
        'name' => 'Blocked Invite',
        'role' => 'company_member',
        'company_id' => $company->id,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(7),
        'created_by' => $creator->id,
    ]);

    actingAs($user)
        ->post(route('core.invites.store'), [
            'email' => 'new.'.Str::lower(Str::random(5)).'@example.com',
            'name' => 'New Invite',
            'role' => 'company_member',
        ])
        ->assertForbidden();

    actingAs($user)
        ->post(route('core.invites.resend', $invite))
        ->assertForbidden();

    actingAs($user)
        ->delete(route('core.invites.destroy', $invite))
        ->assertForbidden();
});

test('company invites allow create resend and revoke with permission', function () {
    Mail::fake();

    [$user, $company] = makeCompanyUserWithInvitePermission(true);

    actingAs($user)
        ->post(route('core.invites.store'), [
            'email' => 'allowed.'.Str::lower(Str::random(5)).'@example.com',
            'name' => 'Allowed Invite',
            'role' => 'company_member',
            'expires_at' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertRedirect(route('core.invites.index'));

    $invite = Invite::query()
        ->where('company_id', $company->id)
        ->latest('created_at')
        ->first();

    expect($invite)->not->toBeNull();

    Mail::assertSent(InviteLinkMail::class);
    Mail::assertSentCount(1);

    actingAs($user)
        ->post(route('core.invites.resend', $invite))
        ->assertRedirect(route('core.invites.index'));

    Mail::assertSentCount(2);

    actingAs($user)
        ->delete(route('core.invites.destroy', $invite))
        ->assertRedirect(route('core.invites.index'));

    expect(
        DB::table('invites')->where('id', $invite->id)->exists()
    )->toBeFalse();
});
