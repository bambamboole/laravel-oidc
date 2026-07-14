<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

beforeEach(function () {
    Cache::clear();
    config()->set('oidc-client.enabled', true);
    config()->set('oidc-client.issuer', 'https://id.example.com');

    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
            'end_session_endpoint' => 'https://id.example.com/oauth/logout',
        ]),
    ]);
});

it('logs out and redirects to the provider end-session endpoint', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $response = $this->actingAs($user)
        ->withSession(['oidc-client.tokens' => ['id_token' => 'the-id-token']])
        ->post(route('logout'));

    $response->assertRedirectContains('https://id.example.com/oauth/logout');
    $response->assertRedirectContains('id_token_hint=the-id-token');
    $this->assertGuest();
});

it('redirects home when the provider has no end-session endpoint', function () {
    config()->set('oidc-client.issuer', 'https://no-end-session.example.com');

    Http::fake([
        'https://no-end-session.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://no-end-session.example.com',
            'authorization_endpoint' => 'https://no-end-session.example.com/oauth/authorize',
            'token_endpoint' => 'https://no-end-session.example.com/oauth/token',
            'jwks_uri' => 'https://no-end-session.example.com/.well-known/jwks.json',
        ]),
    ]);

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('omits id_token_hint when no id_token was stored in the session', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirectContains('https://id.example.com/oauth/logout');
    $location = $response->headers->get('Location');
    expect($location)->not->toContain('id_token_hint');
});

it('persists local logout when provider discovery fails', function () {
    config()->set('oidc-client.issuer', 'https://unavailable.example.com');

    Http::fake([
        'https://unavailable.example.com/.well-known/openid-configuration' => Http::response([], 503),
    ]);

    Route::get('/session-status', fn () => auth()->check() ? 'authenticated' : 'guest');

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->withSession(['oidc-client.tokens' => ['id_token' => 'the-id-token']])
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->get('/session-status')->assertSeeText('guest');
    $this->assertGuest();
});
