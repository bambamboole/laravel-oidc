<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\BackchannelLogoutStore;
use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Bambamboole\LaravelOidcClient\Testing\OidcClientFake;
use Bambamboole\LaravelOidcClient\Token\IdTokenValidator;
use Bambamboole\LaravelOidcClient\Token\LogoutTokenValidator;
use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;

it('installs the fake and stubs discovery, jwks and token endpoints', function () {
    OidcClient::fake();

    expect(config('oidc-client.issuer'))->toBe('https://oidc.test')
        ->and(config('oidc-client.client_id'))->toBe('oidc-client-test');
});

it('mints an id_token the real validator accepts', function () {
    $fake = OidcClient::fake();

    $claims = app(IdTokenValidator::class)->validate($fake->idToken(['sub' => '42']), OidcClientFake::NONCE);

    expect($claims['sub'])->toBe('42')
        ->and($claims['iss'])->toBe('https://oidc.test');
});

it('mints a logout_token the real validator accepts', function () {
    $fake = OidcClient::fake();

    $result = app(LogoutTokenValidator::class)->validate($fake->logoutToken(['sub' => '42', 'sid' => 's1']));

    expect($result['sid'])->toBe('s1')
        ->and($result['sub'])->toBe('42');
});

it('honors an issuer configured before fake() and resets the discovery singleton', function () {
    config()->set('oidc-client.issuer', 'https://custom.test');

    // Resolve discovery first so a stale in-memory metadata memo would survive
    // without the fake's lifecycle reset.
    app(OidcDiscovery::class);

    $fake = OidcClient::fake();

    expect(config('oidc-client.issuer'))->toBe('https://custom.test');

    $claims = app(IdTokenValidator::class)->validate($fake->idToken(), OidcClientFake::NONCE);
    expect($claims['iss'])->toBe('https://custom.test');
});

it('fails the token exchange with the configured status', function () {
    OidcClient::fake()->failTokenExchange(400);

    expect(Http::get('https://oidc.test/oauth/token')->status())->toBe(400);
});

it('drops the end_session_endpoint from discovery', function () {
    OidcClient::fake()->withoutEndSessionEndpoint();

    expect(Http::get('https://oidc.test/.well-known/openid-configuration')->json())
        ->not->toHaveKey('end_session_endpoint');
});

it('mints an id_token the validator rejects when signed by a rogue provider', function () {
    $fake = OidcClient::fake()->withInvalidSignature();

    app(IdTokenValidator::class)->validate($fake->idToken(), OidcClientFake::NONCE);
})->throws(OidcClientException::class);

it('logs a user in through the callback with two lines of setup', function () {
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->loginAs($user))
        ->assertRedirect(config('oidc-client.redirect_after_login', '/dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('drives a failed token exchange to the login route without logging in', function () {
    $fake = OidcClient::fake()->failTokenExchange();
    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->callbackUrl())
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects an id_token signed by a key absent from the jwks', function () {
    $fake = OidcClient::fake()->withInvalidSignature();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())
        ->get($fake->loginAs($user))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('drops the end_session_endpoint so logout falls back home', function () {
    $fake = OidcClient::fake()->withoutEndSessionEndpoint();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('asserts the login redirect went to the provider', function () {
    $fake = OidcClient::fake();

    $fake->assertRedirectedToProvider($this->get(route('login')));
});

it('asserts a user is logged in on the configured guard', function () {
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())->get($fake->loginAs($user));

    $fake->assertLoggedIn($user);
});

it('asserts the code exchange fired after a successful callback', function () {
    $fake = OidcClient::fake();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->withSession($fake->callbackContext())->get($fake->loginAs($user));

    $fake->assertCodeExchanged();
});

it('asserts the code exchange did not fire on a tampered state', function () {
    $fake = OidcClient::fake();

    $this->withSession($fake->callbackContext())
        ->get($fake->callbackUrl(['state' => 'WRONG']))
        ->assertRedirect(route('login'));

    $fake->assertCodeNotExchanged();
});

it('asserts a back-channel logout was processed for a sid', function () {
    $fake = OidcClient::fake();
    app(BackchannelLogoutStore::class)->markRevoked('s-proc');

    $fake->assertBackchannelLogoutProcessed('s-proc');
});

it('keeps flow assertions bound to the factory that actually handled a prior request', function () {
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
