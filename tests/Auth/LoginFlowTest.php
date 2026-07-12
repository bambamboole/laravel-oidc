<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

it('renders the login view through the package seam', function () {
    Oidc::loginView(fn (Request $request) => response('login-view'));

    $this->get('/login')->assertOk()->assertSee('login-view');
});

it('logs a user in with canonicalized credentials and redirects home', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $response = $this->from('/login')->post(route('login.store'), [
        'email' => 'M@Example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('returns Fortify-compatible JSON after login', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->postJson(route('login.store'), [
        'email' => 'm@example.com',
        'password' => 'password',
    ])->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials with a validation error', function () {
    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->from('/login')
        ->post(route('login.store'), ['email' => 'm@example.com', 'password' => 'wrong-password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('sets a remember cookie when remember is requested', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $response = $this->post(route('login.store'), [
        'email' => 'm@example.com',
        'password' => 'password',
        'remember' => true,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertCookie(auth()->guard('web')->getRecallerName());
});

it('throttles repeated login attempts', function () {
    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('login.store'), ['email' => 'm@example.com', 'password' => 'wrong-password']);
    }

    $this->post(route('login.store'), ['email' => 'm@example.com', 'password' => 'wrong-password'])
        ->assertStatus(429);
});
