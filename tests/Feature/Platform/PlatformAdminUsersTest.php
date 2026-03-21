<?php

use App\Core\Access\Models\Invite;
use App\Mail\InviteLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('platform admin creation sends invite and shows pending invite on the admins page', function () {
    Mail::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    actingAs($superAdmin)
        ->post(route('platform.admin-users.store'), [
            'name' => 'Pending Platform Admin',
            'email' => 'pending-platform-admin@example.com',
        ])
        ->assertRedirect(route('platform.admin-users.index'));

    $invite = Invite::query()
        ->where('email', 'pending-platform-admin@example.com')
        ->first();

    expect($invite)->not->toBeNull();
    expect($invite?->role)->toBe('platform_admin');
    expect(User::query()->where('email', 'pending-platform-admin@example.com')->exists())
        ->toBeFalse();

    Mail::assertSent(InviteLinkMail::class);

    actingAs($superAdmin)
        ->get(route('platform.admin-users.index'))
        ->assertOk()
        ->assertSee('Pending Platform Admin')
        ->assertSee('pending-platform-admin@example.com')
        ->assertSee('pending_invite');
});

test('platform admins page can resend pending platform admin invites in place', function () {
    Mail::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $invite = Invite::create([
        'email' => 'resend-platform-admin@example.com',
        'name' => 'Resend Platform Admin',
        'role' => 'platform_admin',
        'company_id' => null,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_FAILED,
        'delivery_attempts' => 1,
        'last_delivery_error' => 'SMTP timeout',
        'created_by' => $superAdmin->id,
    ]);

    actingAs($superAdmin)
        ->from(route('platform.admin-users.index'))
        ->post(route('platform.invites.resend', $invite))
        ->assertRedirect(route('platform.admin-users.index'));

    $invite->refresh();

    expect($invite->delivery_status)->toBe(Invite::DELIVERY_SENT);
    expect($invite->delivery_attempts)->toBe(2);
    expect($invite->last_delivery_error)->toBeNull();

    Mail::assertSent(InviteLinkMail::class);
});

test('platform admins page can cancel pending platform admin invites in place', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $invite = Invite::create([
        'email' => 'cancel-platform-admin@example.com',
        'name' => 'Cancel Platform Admin',
        'role' => 'platform_admin',
        'company_id' => null,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(7),
        'created_by' => $superAdmin->id,
    ]);

    actingAs($superAdmin)
        ->from(route('platform.admin-users.index'))
        ->delete(route('platform.invites.destroy', $invite))
        ->assertRedirect(route('platform.admin-users.index'));

    expect(Invite::query()->whereKey($invite->id)->exists())->toBeFalse();
});

test('accepting a platform admin invite promotes the recipient to superadmin', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $email = 'accepted-platform-admin.'.Str::lower(Str::random(6)).'@example.com';
    $token = Str::random(40);

    Invite::create([
        'email' => $email,
        'name' => 'Accepted Platform Admin',
        'role' => 'platform_admin',
        'company_id' => null,
        'token' => $token,
        'expires_at' => now()->addDays(7),
        'created_by' => $superAdmin->id,
    ]);

    post(route('invites.accept.store', ['token' => $token]), [
        'name' => 'Accepted Platform Admin',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $acceptedUser = User::query()->where('email', $email)->first();

    expect($acceptedUser)->not->toBeNull();
    expect($acceptedUser?->is_super_admin)->toBeTrue();

    actingAs($superAdmin)
        ->get(route('platform.admin-users.index'))
        ->assertOk()
        ->assertSee('Accepted Platform Admin')
        ->assertSee($email)
        ->assertSee('active');
});
