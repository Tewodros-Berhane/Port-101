<?php

use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\DemoScheduledConfirmationNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

test('superadmin can view and update inbound contact requests', function () {
    Notification::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $contactRequest = ContactRequest::create([
        'request_type' => 'demo',
        'full_name' => 'Linus Torvalds',
        'work_email' => 'linus@example.com',
        'company_name' => 'Kernel Works',
        'role_title' => 'Technology Lead',
        'team_size' => '201-500',
        'preferred_demo_date' => now()->addWeek()->toDateString(),
        'modules_interest' => ['projects', 'integrations_governance'],
        'message' => 'Need to review governed delivery and project workflows.',
        'source_page' => '/book-demo',
        'status' => ContactRequest::STATUS_NEW,
    ]);

    actingAs($superAdmin)
        ->get(route('platform.contact-requests.index'))
        ->assertOk()
        ->assertSee('linus@example.com')
        ->assertSee('Kernel Works');

    actingAs($superAdmin)
        ->from(route('platform.contact-requests.index'))
        ->put(route('platform.contact-requests.update', $contactRequest), [
            'status' => ContactRequest::STATUS_DEMO_SCHEDULED,
            'scheduled_demo_date' => now()->addDays(10)->toDateString(),
            'demo_date_change_reason' => 'We aligned the walkthrough with the implementation and finance reviewers.',
        ])
        ->assertRedirect(route('platform.contact-requests.index'))
        ->assertSessionHas('success');

    expect($contactRequest->fresh()->status)->toBe(ContactRequest::STATUS_DEMO_SCHEDULED);
    expect($contactRequest->fresh()->scheduled_demo_date?->toDateString())->toBe(now()->addDays(10)->toDateString());
    expect($contactRequest->fresh()->demo_date_change_reason)->toBe(
        'We aligned the walkthrough with the implementation and finance reviewers.'
    );

    Notification::assertSentOnDemand(
        DemoScheduledConfirmationNotification::class,
        function ($notification, array $channels, object $notifiable) use ($contactRequest): bool {
            return in_array('mail', $channels, true)
                && ($notifiable->routes['mail'] ?? null) === $contactRequest->work_email
                && $notification->contactRequest->scheduled_demo_date?->toDateString() === now()->addDays(10)->toDateString()
                && $notification->reason === 'We aligned the walkthrough with the implementation and finance reviewers.';
        }
    );
});

test('changing the confirmed demo date away from the requested date requires a reason', function () {
    Notification::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $contactRequest = ContactRequest::create([
        'request_type' => 'demo',
        'full_name' => 'Grace Hopper',
        'work_email' => 'grace@example.com',
        'company_name' => 'Compiler Works',
        'role_title' => 'Finance Systems Director',
        'team_size' => '51-200',
        'preferred_demo_date' => now()->addWeek()->toDateString(),
        'modules_interest' => ['accounting', 'approvals_reporting'],
        'message' => 'Need to review finance and approval controls.',
        'source_page' => '/book-demo',
        'status' => ContactRequest::STATUS_NEW,
    ]);

    actingAs($superAdmin)
        ->from(route('platform.contact-requests.index'))
        ->put(route('platform.contact-requests.update', $contactRequest), [
            'status' => ContactRequest::STATUS_DEMO_SCHEDULED,
            'scheduled_demo_date' => now()->addDays(10)->toDateString(),
        ])
        ->assertRedirect(route('platform.contact-requests.index'))
        ->assertSessionHasErrors('demo_date_change_reason');

    expect($contactRequest->fresh()->scheduled_demo_date)->toBeNull();

    Notification::assertNothingSent();
});

test('non superadmins cannot access inbound contact requests management', function () {
    $user = User::factory()->create([
        'is_super_admin' => false,
    ]);

    actingAs($user)
        ->get(route('platform.contact-requests.index'))
        ->assertForbidden();
});
