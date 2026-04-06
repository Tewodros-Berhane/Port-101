<?php

use App\Core\Access\Models\Invite;
use App\Core\Company\Models\Company;
use App\Mail\InviteLinkMail;
use App\Mail\PlatformOperationsReportDeliveryMail;
use App\Models\User;

it('renders the invite email with Port-101 branding', function () {
    $owner = User::factory()->create();

    $company = Company::create([
        'name' => 'Acme ERP',
        'slug' => 'acme-erp-mail',
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);

    $invite = new Invite([
        'email' => 'owner@example.com',
        'name' => 'Owner User',
        'role' => 'company_owner',
        'token' => 'invite-token',
        'expires_at' => now()->addDays(7),
    ]);
    $invite->setRelation('company', $company);

    $html = (new InviteLinkMail($invite, 'http://localhost:8000/invites/invite-token'))->render();

    expect($html)
        ->toContain('Port-101')
        ->toContain('Operational ERP for finance, inventory, projects, and HR')
        ->toContain('Workspace Access')
        ->toContain('You are invited to Port-101')
        ->toContain('Accept invite')
        ->toContain('Acme ERP');
});

it('renders the scheduled operations report email with branded metrics', function () {
    $html = (new PlatformOperationsReportDeliveryMail(
        presetName: 'Daily Governance Review',
        periodLabel: 'Last 24 hours',
        format: 'csv',
        summary: [
            'admin_actions' => 12,
            'delivery_total' => 8,
            'failed' => 1,
            'failure_rate' => 12.5,
        ],
        links: [
            'admin_actions' => '/platform/reports/admin-actions',
            'delivery_trends' => '/platform/reports/delivery-trends',
        ],
    ))->render();

    expect($html)
        ->toContain('Port-101')
        ->toContain('Scheduled Delivery')
        ->toContain('Operations reports are ready')
        ->toContain('Daily Governance Review')
        ->toContain('Open admin actions export')
        ->toContain('Open delivery trends export')
        ->toContain('12.5%');
});
