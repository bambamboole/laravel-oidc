<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\BackchannelLogoutStore;
use Bambamboole\LaravelOidc\Client\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Bambamboole\LaravelOidc\Client\Facades\OidcClient;
use Bambamboole\LaravelOidc\Client\Testing\OidcClientFake;
use Bambamboole\LaravelOidc\Client\Token\IdTokenValidator;
use Bambamboole\LaravelOidc\Client\Token\LogoutTokenValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;

it('installs the fake and stubs discovery, jwks and token endpoints', function (): void {
    OidcClient::fake();

    expect(config('oidc-client.issuer'))->toBe('https://oidc.test')
        ->and(config('oidc-client.client_id'))->toBe('oidc-client-test');
});

it('blocks unstubbed requests from reaching the network', function (): void {
    OidcClient::fake();

    expect(fn () => Http::get('https://unrelated.example/api'))
        ->toThrow(RuntimeException::class, 'unrelated.example');
});

it('mints an id_token the real validator accepts', function (): void {
    $fake = OidcClient::fake();

    $claims = app(IdTokenValidator::class)->validate($fake->idToken(['sub' => '42']), OidcClientFake::NONCE);

    expect($claims['sub'])->toBe('42')
        ->and($claims['iss'])->toBe('https://oidc.test');
});

it('mints a logout_token the real validator accepts', function (): void {
    $fake = OidcClient::fake();

    $result = app(LogoutTokenValidator::class)->validate($fake->logoutToken(['sub' => '42', 'sid' => 's1']));

    expect($result['sid'])->toBe('s1')
        ->and($result['sub'])->toBe('42');
});

it('honors an issuer configured before fake() and resets the discovery singleton', function (): void {
    config()->set('oidc-client.issuer', 'https://custom.test');

    // Resolve discovery first so a stale in-memory metadata memo would survive
    // without the fake's lifecycle reset.
    app(OidcDiscovery::class);

    $fake = OidcClient::fake();

    expect(config('oidc-client.issuer'))->toBe('https://custom.test');

    $claims = app(IdTokenValidator::class)->validate($fake->idToken(), OidcClientFake::NONCE);
    expect($claims['iss'])->toBe('https://custom.test');
});

it('mints tokens at the frozen Carbon test time', function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');
    $frozen = Carbon::now()->getTimestamp();
    $fake = OidcClient::fake();

    $decode = fn (string $jwt): array => json_decode(
        base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')),
        true,
    );

    expect((int) $decode($fake->idToken())['iat'])->toBe($frozen)
        ->and((int) $decode($fake->logoutToken())['iat'])->toBe($frozen);
});

it('fails the token exchange with the configured status', function (): void {
    OidcClient::fake()->failTokenExchange(400);

    expect(Http::get('https://oidc.test/oauth/token')->status())->toBe(400);
});

it('drops the end_session_endpoint from discovery', function (): void {
    OidcClient::fake()->withoutEndSessionEndpoint();

    expect(Http::get('https://oidc.test/.well-known/openid-configuration')->json())
        ->not->toHaveKey('end_session_endpoint');
});

it('mints an id_token the validator rejects when signed by a rogue provider', function (): void {
    $fake = OidcClient::fake()->withInvalidSignature();

    app(IdTokenValidator::class)->validate($fake->idToken(), OidcClientFake::NONCE);
})->throws(OidcClientException::class);

it('logs a user in through the callback with two lines of setup', function (): void {
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->loginAs($user))
        ->assertRedirect(config('oidc-client.redirect_after_login', '/dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('drives a failed token exchange to the login route without logging in', function (): void {
    $fake = OidcClient::fake()->failTokenExchange();
    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->callbackUrl())
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects an id_token signed by a key absent from the jwks', function (): void {
    $fake = OidcClient::fake()->withInvalidSignature();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->loginAs($user))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('drops the end_session_endpoint so logout falls back home', function (): void {
    $fake = OidcClient::fake()->withoutEndSessionEndpoint();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('asserts the login redirect went to the provider', function (): void {
    $fake = OidcClient::fake();

    $fake->assertRedirectedToProvider($this->get(route('login')));
});

it('asserts a user is logged in on the configured guard', function (): void {
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())->get($fake->loginAs($user));

    $fake->assertLoggedIn($user);
});

it('asserts the code exchange fired after a successful callback', function (): void {
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())->get($fake->loginAs($user));

    $fake->assertCodeExchanged();
});

it('asserts the code exchange did not fire on a tampered state', function (): void {
    $fake = OidcClient::fake();

    $this->withSession($fake->callbackContext())
        ->get($fake->callbackUrl(['state' => 'WRONG']))
        ->assertRedirect(route('login'));

    $fake->assertCodeNotExchanged();
});

it('asserts a back-channel logout was processed for a sid', function (): void {
    $fake = OidcClient::fake();
    app(BackchannelLogoutStore::class)->markRevoked('s-proc');

    $fake->assertBackchannelLogoutProcessed('s-proc');
});

it('keeps flow assertions bound to the factory that actually handled a prior request', function (): void {
    // Regression test for the factory-swap bug: applyHttpStubs() used to
    // forget/rebuild the HttpFactory singleton on every customizer call. A
    // customizer invoked after RelyingParty had already resolved the old
    // factory left assertCodeExchanged()/assertCodeNotExchanged() reading
    // the newest (unrelated, empty) factory's request history instead of
    // the one RelyingParty actually made the request through.
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->loginAs($user))
        ->assertRedirect(config('oidc-client.redirect_after_login', '/dashboard'));

    $this->assertAuthenticatedAs($user);

    // Apply a customizer after the exchange already happened.
    $fake->withoutEndSessionEndpoint();

    $fake->assertCodeExchanged();
});
