<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    [$user] = makeActiveCompanyMember();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('company.dashboard'));
});
