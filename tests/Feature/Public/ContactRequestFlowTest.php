<?php

use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactRequestSubmittedNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;

test('public demo request is stored and notifies superadmins', function () {
    Notification::fake();

    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    post(route('public.contact-requests.store'), [
        'request_type' => 'demo',
        'full_name' => 'Ada Lovelace',
        'work_email' => 'ada@example.com',
        'company_name' => 'Analytical Engines Ltd',
        'role_title' => 'Operations Director',
        'team_size' => '11-50',
        'preferred_demo_date' => now()->addWeek()->toDateString(),
        'modules_interest' => ['sales', 'inventory', 'accounting'],
        'message' => 'We want to review cross-functional operations and accounting workflows.',
        'phone' => '+1 555 0100',
        'country' => 'United States',
        'source_page' => '/book-demo',
        'website' => '',
    ])
        ->assertRedirect(route('public.book-demo'))
        ->assertSessionHas('success');

    $request = ContactRequest::query()
        ->where('work_email', 'ada@example.com')
        ->first();

    expect($request)->not->toBeNull();
    expect($request?->request_type)->toBe('demo');
    expect($request?->status)->toBe(ContactRequest::STATUS_NEW);
    expect($request?->preferred_demo_date?->toDateString())->toBe(now()->addWeek()->toDateString());
    expect($request?->modules_interest)->toBe(['sales', 'inventory', 'accounting']);

    Notification::assertSentTo($superAdmin, ContactRequestSubmittedNotification::class);
});

test('demo requests require a preferred demo date', function () {
    post(route('public.contact-requests.store'), [
        'request_type' => 'demo',
        'full_name' => 'Ada Lovelace',
        'work_email' => 'ada@example.com',
        'company_name' => 'Analytical Engines Ltd',
        'role_title' => 'Operations Director',
        'team_size' => '11-50',
        'website' => '',
    ])
        ->assertSessionHasErrors('preferred_demo_date');
});

test('recent duplicate public requests are suppressed', function () {
    ContactRequest::create([
        'request_type' => 'sales',
        'full_name' => 'Grace Hopper',
        'work_email' => 'grace@example.com',
        'company_name' => 'Compiler Co',
        'role_title' => 'Finance Lead',
        'team_size' => '51-200',
        'modules_interest' => ['accounting'],
        'message' => 'Initial inquiry.',
        'source_page' => '/contact-sales',
        'status' => ContactRequest::STATUS_NEW,
    ]);

    post(route('public.contact-requests.store'), [
        'request_type' => 'sales',
        'full_name' => 'Grace Hopper',
        'work_email' => 'grace@example.com',
        'company_name' => 'Compiler Co',
        'role_title' => 'Finance Lead',
        'team_size' => '51-200',
        'modules_interest' => ['accounting'],
        'message' => 'Follow up inquiry.',
        'source_page' => '/contact-sales',
        'website' => '',
    ])
        ->assertRedirect(route('public.contact-sales'))
        ->assertSessionHas('warning');

    expect(
        ContactRequest::query()
            ->where('work_email', 'grace@example.com')
            ->count()
    )->toBe(1);
});
