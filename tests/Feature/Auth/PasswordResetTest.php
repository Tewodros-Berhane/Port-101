<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password link request is outwardly indistinguishable for existing and non-existing emails', function () {
    Notification::fake();

    $user = User::factory()->create([
        'remember_token' => 'existing-remember-token',
    ]);

    $existingResponse = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email]);

    $missingResponse = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'missing-user@example.com']);

    $expectedMessage = trans(\Illuminate\Support\Facades\Password::RESET_LINK_SENT);

    $existingResponse
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', $expectedMessage);

    $missingResponse
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', $expectedMessage);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'remember_token' => 'old-reset-token',
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $oldPasswordHash = $user->getAuthPassword();

        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $freshUser = $user->fresh();

        expect($freshUser)->not->toBeNull();
        expect(Hash::check('password', $freshUser->password))->toBeTrue();
        expect($freshUser?->remember_token)->not->toBe('old-reset-token');

        $this->actingAs($freshUser)
            ->withSession(['password_hash_web' => $oldPasswordHash])
            ->get(route('user-password.edit'))
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
