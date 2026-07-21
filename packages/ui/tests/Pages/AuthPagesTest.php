<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

/**
 * Every AuthViewManager seam renders through the server package's real
 * `identity.*` routes. Requests are sent with the `X-Inertia` header so
 * Inertia returns the page payload as JSON instead of trying to render a
 * host application's root Blade view (which this package does not ship),
 * and `assertSee(..., false)` checks the payload for the translated string
 * without HTML-escaping the expectation first.
 */
it('renders the login page', function () {
    $this->get(route('identity.login'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.login.title'), false);
});

it('renders the register page', function () {
    $this->get(route('identity.register'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.register.title'), false);
});

it('renders the forgot-password page', function () {
    $this->get(route('identity.password.request'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.forgot-password.title'), false);
});

it('renders the reset-password page', function () {
    $this->get(route('identity.password.reset', ['token' => 'dummy-token']), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.reset-password.title'), false);
});

it('renders the verify-email page for an unverified authenticated user', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user, 'identity')
        ->get(route('identity.verification.notice'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.verify-email.title'), false);
});

it('renders the confirm-password page for an authenticated user', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user, 'identity')
        ->get(route('identity.password.confirm'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.confirm-password.title'), false);
});

it('renders the two-factor challenge page for a pending login', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->withSession(['login.id' => $user->getAuthIdentifier()])
        ->get(route('identity.two-factor.login'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.two-factor.title'), false);
});
