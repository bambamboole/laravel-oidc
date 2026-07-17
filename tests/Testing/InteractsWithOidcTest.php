<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\AuthenticationMethods;
use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Testing\InteractsWithOidc;
use Bambamboole\LaravelOidc\Testing\PkcePair;
use Bambamboole\LaravelOidc\Token\TokenInspector;
use Workbench\App\Models\User;

uses(InteractsWithOidc::class);

beforeEach(function () {
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'email_verified_at' => now(), 'password' => 'x']);
});

it('authenticates on the identity guard and seeds the auth context session keys', function () {
    $result = $this->actingAsIdentity(
        $this->user,
        idTokenClaims: ['locale' => 'de'],
        accessTokenClaims: ['tenant' => 't1'],
        amr: ['pwd', 'otp'],
        authTime: 1234567890,
    );

    expect($result)->toBe($this)
        ->and(auth('identity')->id())->toBe($this->user->id)
        ->and(session('oidc.auth_time'))->toBe(1234567890)
        ->and(session(AuthenticationMethods::SESSION_KEY))->toBe(['pwd', 'otp'])
        ->and(session('oidc.id_token_claims'))->toBe(['locale' => 'de'])
        ->and(session('oidc.access_token_claims'))->toBe(['tenant' => 't1']);
});

it('defaults auth_time to now and leaves optional context keys unset', function () {
    $this->actingAsIdentity($this->user);

    expect(session('oidc.auth_time'))->toBeGreaterThanOrEqual(time() - 5)
        ->and(session()->has(AuthenticationMethods::SESSION_KEY))->toBeFalse()
        ->and(session()->has('oidc.id_token_claims'))->toBeFalse()
        ->and(session()->has('oidc.access_token_claims'))->toBeFalse();
});

it('creates an authorization-code grant client with sane defaults', function () {
    $client = $this->createOidcClient();

    expect($client->exists)->toBeTrue()
        ->and($client->redirect_uris)->toBe(['https://rp.test/callback'])
        ->and($client->confidential())->toBeTrue()
        ->and($client->plainSecret)->toBeString();
});

it('configures a first-party client without any singleton busting', function () {
    // Resolve the manager first to prove no forgetInstance ceremony is needed.
    Oidc::issuer();

    $client = $this->withFirstPartyClient();

    expect(config('oidc.first_party.client_id'))->toBe((string) $client->getKey())
        ->and(config('oidc.first_party.trusted'))->toBeTrue();
});

it('generates an RFC 7636 S256 pkce pair', function () {
    $pair = $this->pkce();

    expect($pair)->toBeInstanceOf(PkcePair::class)
        ->and(strlen($pair->verifier))->toBe(64)
        ->and($pair->challenge)->toBe(rtrim(strtr(base64_encode(hash('sha256', $pair->verifier, true)), '+/', '-_'), '='));
});

it('mints a real signed access token with a persisted row', function () {
    $jwt = $this->issueTokenFor($this->user, scopes: ['openid', 'email'], audience: ['https://api.orders.test']);

    $token = app(TokenInspector::class)->accessToken($jwt);

    expect($token)->not->toBeNull()
        ->and((string) $token->getAttribute('user_id'))->toBe((string) $this->user->id)
        ->and($token->getAttribute('scopes'))->toBe(['openid', 'email'])
        ->and($token->getAttribute('revoked'))->toBeFalse();

    // Passport's TokenGuard resolves the requesting client from aud[0] (see
    // League's BearerTokenValidator), so a custom resource audience isn't
    // valid for this server's own protected routes — mint a second,
    // default-audience token for the /oauth/userinfo round trip.
    $bearerJwt = $this->issueTokenFor($this->user, scopes: ['openid', 'email']);
    $bearer = $this->withHeader('Authorization', 'Bearer '.$bearerJwt)->get('/oauth/userinfo');
    $bearer->assertOk()->assertJsonPath('sub', (string) $this->user->id);
});

it('memoizes one default client across issueTokenFor calls', function () {
    $first = $this->issueTokenFor($this->user);
    $second = $this->issueTokenFor($this->user);

    $inspector = app(TokenInspector::class);

    expect($inspector->accessToken($first)->getAttribute('client_id'))->toBe($inspector->accessToken($second)->getAttribute('client_id'));
});
