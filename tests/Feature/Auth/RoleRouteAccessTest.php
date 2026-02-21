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

test('company users cannot access platform routes by direct url', function () {
    [$companyUser] = makeActiveCompanyMember();

    $this->actingAs($companyUser)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});

