<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Bambamboole\LaravelOidc\Client\Testing\FakeOidcProvider;
use Bambamboole\LaravelOidc\Client\Token\IdTokenValidator;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signatureAlgClaims(array $overrides = []): array
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

function signatureAlgB64Url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
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
            'keys' => array_merge(
                $this->provider->rsaJwks('key-1'),
                $this->provider->ecJwks('key-2'),
                array_map(
                    fn (array $jwk): array => array_merge($jwk, ['kid' => 'key-3', 'alg' => 'PS256']),
                    $this->provider->rsaJwks('key-3'),
                ),
            ),
        ]),
    ]);
});

it('accepts an ES256-signed id token', function () {
    $jwt = $this->provider->idToken(signatureAlgClaims(), 'key-2', 'ES256');

    $claims = app(IdTokenValidator::class)->validate($jwt, 'the-nonce');

    expect($claims['sub'])->toBe('42');
});

it('rejects a token whose header claims HS256', function () {
    $header = signatureAlgB64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'kid' => 'key-1'], JSON_THROW_ON_ERROR));
    $payload = signatureAlgB64Url((string) json_encode(signatureAlgClaims(), JSON_THROW_ON_ERROR));
    $signature = signatureAlgB64Url(hash_hmac('sha256', $header.'.'.$payload, 'attacker-known-secret', true));

    app(IdTokenValidator::class)->validate($header.'.'.$payload.'.'.$signature, 'the-nonce');
})->throws(OidcClientException::class, 'signature is invalid');

it('rejects an unsigned token with alg none', function () {
    $header = signatureAlgB64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'none', 'kid' => 'key-1'], JSON_THROW_ON_ERROR));
    $payload = signatureAlgB64Url((string) json_encode(signatureAlgClaims(), JSON_THROW_ON_ERROR));

    app(IdTokenValidator::class)->validate($header.'.'.$payload.'.', 'the-nonce');
})->throws(OidcClientException::class, 'could not be parsed');

it('rejects a token bound to a JWK with an algorithm outside the allow-list', function () {
    $jwt = $this->provider->idToken(signatureAlgClaims(), 'key-3');

    app(IdTokenValidator::class)->validate($jwt, 'the-nonce');
})->throws(OidcClientException::class, 'not supported');
