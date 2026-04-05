<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password update page is displayed', function () {
    [$user] = makeActiveCompanyMember();

    $response = $this
        ->actingAs($user)
        ->get(route('user-password.edit'));

    $response->assertOk();
});

test('password can be updated', function () {
    [$user] = makeActiveCompanyMember();

    $user->forceFill([
        'remember_token' => 'old-remember-token',
    ])->save();

    $oldPasswordHash = $user->getAuthPassword();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'));

    $freshUser = $user->fresh();

    expect(Hash::check('new-password', $freshUser->password))->toBeTrue();
    expect($freshUser->remember_token)->not->toBe('old-remember-token');

    $this->actingAs($freshUser)
        ->withSession(['password_hash_web' => $oldPasswordHash])
        ->get(route('user-password.edit'))
        ->assertRedirect(route('login'));
});

test('correct password must be provided to update password', function () {
    [$user] = makeActiveCompanyMember();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('user-password.edit'));
});
