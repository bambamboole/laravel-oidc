<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Bambamboole\LaravelOidcClient\Tests\Support\FakeOidcProvider;
use Bambamboole\LaravelOidcClient\Token\LogoutTokenValidator;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeLogoutClaims(array $overrides = []): array
{
    return array_merge([
        'iss' => 'https://id.example.com',
        'aud' => 'client-123',
        'sub' => '42',
        'sid' => 'sess-abc',
        'iat' => time(),
        'exp' => time() + 120,
        'jti' => 'jti-1',
        'events' => ['http://schemas.openid.net/event/backchannel-logout' => (object) []],
    ], $overrides);
}

beforeEach(function () {
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'client-123');
    $this->provider = new FakeOidcProvider;
    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
        ]),
        'https://id.example.com/.well-known/jwks.json' => Http::response([
            'keys' => $this->provider->rsaJwks('key-1'),
        ]),
    ]);
});

it('accepts a well-formed logout token and returns sid + sub', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(), 'key-1');

    $result = app(LogoutTokenValidator::class)->validate($jwt);

    expect($result['sid'])->toBe('sess-abc')->and($result['sub'])->toBe('42');
});

it('rejects a logout token that carries a nonce', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(['nonce' => 'x']), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class, 'nonce');

it('rejects a token without the backchannel-logout event', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(['events' => ['other' => (object) []]]), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class);

it('rejects a token without a sid', function () {
    $claims = fakeLogoutClaims();
    unset($claims['sid']);
    app(LogoutTokenValidator::class)->validate($this->provider->logoutToken($claims, 'key-1'));
})->throws(OidcClientException::class);

it('rejects a wrong audience', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(['aud' => 'someone-else']), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class);

it('rejects an expired token', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(['exp' => time() - 3600, 'iat' => time() - 3700]), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class);

it('rejects a token that is missing the logout+jwt typ header', function () {
    $jwt = $this->provider->idToken(fakeLogoutClaims(), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class, 'typ');

it('rejects a logout token issued implausibly far in the past', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(['iat' => time() - 3600, 'exp' => time() + 3600]), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class);

it('rejects a token signed with an untrusted key', function () {
    $otherProvider = new FakeOidcProvider;
    $jwt = $otherProvider->logoutToken(fakeLogoutClaims(), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class);

it('rejects a token with the wrong issuer', function () {
    $jwt = $this->provider->logoutToken(fakeLogoutClaims(['iss' => 'https://someone-else.example.com']), 'key-1');
    app(LogoutTokenValidator::class)->validate($jwt);
})->throws(OidcClientException::class, 'issuer');
