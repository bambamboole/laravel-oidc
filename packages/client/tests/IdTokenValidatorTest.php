<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Bambamboole\LaravelOidcClient\Testing\FakeOidcProvider;
use Bambamboole\LaravelOidcClient\Token\IdTokenValidator;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fakeProviderClaims(array $overrides = []): array
{
    return array_merge([
        'iss' => 'https://id.example.com',
        'aud' => 'client-123',
        'sub' => '42',
        'nonce' => 'the-nonce',
        'iat' => time(),
        'nbf' => time(),
        'exp' => time() + 300,
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

it('accepts a well-formed id token and returns its claims', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(), 'key-1');

    $claims = app(IdTokenValidator::class)->validate($jwt, 'the-nonce');

    expect($claims['sub'])->toBe('42')
        ->and($claims['iss'])->toBe('https://id.example.com');
});

it('rejects a token whose nonce does not match', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'different-nonce');
})->throws(OidcClientException::class, 'nonce does not match');

it('rejects a token with the wrong audience', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(['aud' => 'someone-else']), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'audience does not include');

it('rejects a token with the wrong issuer', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(['iss' => 'https://evil.example.com']), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'issuer does not match');

it('rejects an expired token', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(['exp' => time() - 3600]), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'has expired');

it('rejects a token signed with an unknown kid', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(), 'unknown-kid');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'No JWKS key matches');

it('rejects a token missing a subject', function () {
    $jwt = $this->provider->idToken(fakeProviderClaims(['sub' => '']), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'missing a subject');

it('rejects a token missing exp', function () {
    $jwt = $this->provider->idToken(array_diff_key(fakeProviderClaims(), ['exp' => true]), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'missing or invalid exp');

it('rejects a token missing iat', function () {
    $jwt = $this->provider->idToken(array_diff_key(fakeProviderClaims(), ['iat' => true]), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'missing or invalid iat');

it('rejects invalid timestamp claim shapes', function (string $claim) {
    $jwt = $this->provider->rawIdToken(fakeProviderClaims([$claim => 'not-a-timestamp']), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->with(['exp', 'iat', 'nbf'])->throws(OidcClientException::class);

it('rejects a token issued in the future outside leeway', function () {
    config()->set('oidc-client.leeway', 60);
    $jwt = $this->provider->idToken(fakeProviderClaims(['iat' => time() + 61]), 'key-1');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'issued in the future');
