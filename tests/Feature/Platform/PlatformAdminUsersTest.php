<?php

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\Company;
use App\Mail\InviteLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
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

test('generic platform invite pages are not available to superadmins', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    actingAs($superAdmin)
        ->get('/platform/invites')
        ->assertNotFound();

    actingAs($superAdmin)
        ->get('/platform/invites/create')
        ->assertNotFound();
});

test('platform invite management endpoints reject company owner invites', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $company = Company::create([
        'name' => 'Owner Invite Company '.Str::upper(Str::random(4)),
        'slug' => 'owner-invite-company-'.Str::lower(Str::random(8)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $superAdmin->id,
    ]);

    $invite = Invite::create([
        'email' => 'owner-invite@example.com',
        'name' => 'Owner Invite',
        'role' => 'company_owner',
        'company_id' => $company->id,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(7),
        'created_by' => $superAdmin->id,
    ]);

    actingAs($superAdmin)
        ->post(route('platform.invites.resend', $invite))
        ->assertNotFound();

    actingAs($superAdmin)
        ->delete(route('platform.invites.destroy', $invite))
        ->assertNotFound();
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

test('platform admin invite creation is throttled', function () {
    Mail::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    foreach (range(1, 5) as $attempt) {
        actingAs($superAdmin)
            ->from(route('platform.admin-users.index'))
            ->post(route('platform.admin-users.store'), [
                'name' => "Platform Admin {$attempt}",
                'email' => "platform-admin-throttle-{$attempt}@example.com",
            ])
            ->assertRedirect(route('platform.admin-users.index'));
    }

    actingAs($superAdmin)
        ->from(route('platform.admin-users.index'))
        ->post(route('platform.admin-users.store'), [
            'name' => 'Platform Admin 6',
            'email' => 'platform-admin-throttle-6@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning', 'Too many requests were sent from this browser or account in a short period.');

    expect(
        Invite::query()
            ->whereRaw('LOWER(email) like ?', ['platform-admin-throttle-%@example.com'])
            ->count()
    )->toBe(5);

    Mail::assertSentCount(5);
});

test('platform invite resend is throttled', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $invite = Invite::create([
        'email' => 'platform-resend-throttle@example.com',
        'name' => 'Platform Resend Throttle',
        'role' => 'platform_admin',
        'company_id' => null,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(7),
        'created_by' => $superAdmin->id,
    ]);

    foreach (range(1, 10) as $attempt) {
        actingAs($superAdmin)
            ->from(route('platform.admin-users.index'))
            ->post(route('platform.invites.resend', $invite))
            ->assertRedirect(route('platform.admin-users.index'));
    }

    actingAs($superAdmin)
        ->from(route('platform.admin-users.index'))
        ->post(route('platform.invites.resend', $invite))
        ->assertRedirect(route('platform.admin-users.index'))
        ->assertSessionHas('warning', 'Too many requests were sent from this browser or account in a short period.');

    expect($invite->fresh()->delivery_attempts)->toBe(10);
});

test('platform admin invite creation refreshes existing pending invites instead of duplicating them', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    actingAs($superAdmin)
        ->post(route('platform.admin-users.store'), [
            'name' => 'Platform Duplicate First',
            'email' => 'platform-duplicate@example.com',
        ])
        ->assertRedirect(route('platform.admin-users.index'));

    $firstInvite = Invite::query()
        ->where('role', 'platform_admin')
        ->whereNull('company_id')
        ->where('email', 'platform-duplicate@example.com')
        ->firstOrFail();

    actingAs($superAdmin)
        ->post(route('platform.admin-users.store'), [
            'name' => 'Platform Duplicate Refreshed',
            'email' => 'platform-duplicate@example.com',
        ])
        ->assertRedirect(route('platform.admin-users.index'));

    $matchingInvites = Invite::query()
        ->where('role', 'platform_admin')
        ->whereNull('company_id')
        ->whereRaw('LOWER(email) = ?', ['platform-duplicate@example.com'])
        ->get();

    expect($matchingInvites)->toHaveCount(1);

    $refreshedInvite = $matchingInvites->first();

    expect($refreshedInvite?->id)->toBe($firstInvite->id)
        ->and($refreshedInvite?->name)->toBe('Platform Duplicate Refreshed')
        ->and($refreshedInvite?->token)->not->toBe($firstInvite->token);
});
