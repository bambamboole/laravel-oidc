<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

beforeEach(function () {
    Cache::clear();
    $this->fake = OidcClient::fake();
});

it('logs out and redirects to the provider end-session endpoint', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $response = $this->actingAs($user)
        ->withSession(['oidc-client.tokens' => ['id_token' => 'the-id-token']])
        ->post(route('logout'));

    $response->assertRedirectContains(config('oidc-client.issuer').'/oauth/logout');
    $response->assertRedirectContains('id_token_hint=the-id-token');
    $this->assertGuest();
});

it('redirects home when the provider has no end-session endpoint', function () {
    $this->fake->withoutEndSessionEndpoint();

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});

it('omits id_token_hint when no id_token was stored in the session', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirectContains(config('oidc-client.issuer').'/oauth/logout');
    $location = $response->headers->get('Location');
    expect($location)->not->toContain('id_token_hint');
});

it('persists local logout when provider discovery fails', function () {
    // The fake models success responses only; a real transport failure needs a
    // raw Http::fake() against a dedicated issuer.
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
