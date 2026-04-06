<?php

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\Company;
use App\Jobs\SendInviteLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('platform company creation queues an owner invite', function () {
    Queue::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    actingAs($superAdmin)
        ->post(route('platform.companies.store'), [
            'name' => 'Invite Ready Co',
            'slug' => 'invite-ready-co-'.Str::lower(Str::random(6)),
            'timezone' => 'UTC',
            'currency_code' => 'USD',
            'is_active' => true,
            'owner_name' => 'Invite Ready Owner',
            'owner_email' => 'invite-ready-owner@example.com',
        ])
        ->assertRedirect();

    $company = Company::query()
        ->where('name', 'Invite Ready Co')
        ->firstOrFail();

    $invite = Invite::query()
        ->where('company_id', $company->id)
        ->where('role', 'company_owner')
        ->whereRaw('LOWER(email) = ?', ['invite-ready-owner@example.com'])
        ->first();

    expect($invite)->not->toBeNull();

    Queue::assertPushed(SendInviteLinkMail::class, function (SendInviteLinkMail $job) use ($invite) {
        return $job->inviteId === $invite?->id;
    });
});

test('superadmin can refresh an expired owner invite from the platform company page', function () {
    Queue::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $owner = User::factory()->create([
        'name' => 'Platform Managed Owner',
        'email' => 'platform-managed-owner@example.com',
    ]);

    $company = Company::create([
        'name' => 'Platform Invite Company '.Str::upper(Str::random(4)),
        'slug' => 'platform-invite-company-'.Str::lower(Str::random(8)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $owner->id,
        'is_active' => true,
    ]);

    $invite = Invite::create([
        'email' => $owner->email,
        'name' => $owner->name,
        'role' => 'company_owner',
        'company_id' => $company->id,
        'token' => Str::random(40),
        'expires_at' => now()->subDay(),
        'delivery_status' => Invite::DELIVERY_FAILED,
        'delivery_attempts' => 2,
        'last_delivery_error' => 'SMTP timeout',
        'created_by' => $superAdmin->id,
    ]);

    $originalToken = $invite->token;

    actingAs($superAdmin)
        ->get(route('platform.companies.show', $company))
        ->assertOk()
        ->assertSee('platform-managed-owner@example.com');

    actingAs($superAdmin)
        ->post(route('platform.companies.owner-invite.send', $company))
        ->assertRedirect(route('platform.companies.show', $company));

    $invite->refresh();

    expect($invite->token)->not->toBe($originalToken)
        ->and($invite->delivery_status)->toBe(Invite::DELIVERY_PENDING)
        ->and($invite->last_delivery_error)->toBeNull()
        ->and($invite->expires_at?->isFuture())->toBeTrue();

    Queue::assertPushed(SendInviteLinkMail::class, function (SendInviteLinkMail $job) use ($invite) {
        return $job->inviteId === $invite->id;
    });
});
