<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Workbench\App\Models\User;

it('sends a password reset link through the Laravel broker', function () {
    Notification::fake();

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('old-password')]);

    $this->from('/forgot-password')
        ->post(route('password.email'), ['email' => 'm@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets a password through the package action seam and logs the user in', function () {
    Event::fake([PasswordReset::class]);

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('old-password')]);
    $token = Password::broker()->createToken($user);

    Oidc::resetUserPasswordsUsing(function (CanResetPassword $user, array $input): void {
        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'm@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));

    $this->assertAuthenticatedAs($user->fresh());
    expect(Hash::check('new-password', (string) $user->fresh()->password))->toBeTrue();
    Event::assertDispatched(PasswordReset::class);
});

it('returns validation errors for an invalid reset token', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('old-password')]);

    Oidc::resetUserPasswordsUsing(function (CanResetPassword $user, array $input): void {
        $user->forceFill(['password' => Hash::make($input['password'])])->save();
    });

    $this->from('/reset-password/invalid-token')
        ->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect('/reset-password/invalid-token')
        ->assertSessionHasErrors('email');
});
