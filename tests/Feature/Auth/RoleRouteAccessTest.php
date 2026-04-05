<?php

use App\Models\User;

test('superadmins are redirected away from company workspace routes', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('company.dashboard'))
        ->assertRedirect(route('platform.dashboard'));
});

test('superadmins are redirected away from company master data routes', function () {
    $superAdmin = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('core.partners.index'))
        ->assertRedirect(route('platform.dashboard'));

    $this->actingAs($superAdmin)
        ->get(route('core.audit-logs.index'))
        ->assertRedirect(route('platform.dashboard'));

    $this->actingAs($superAdmin)
        ->get(route('core.audit-logs.export', ['format' => 'json']))
        ->assertRedirect(route('platform.dashboard'));
});

test('company users cannot access platform routes by direct url', function () {
    [$companyUser] = makeActiveCompanyMember();

    $this->actingAs($companyUser)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});
