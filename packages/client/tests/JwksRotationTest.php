<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Bambamboole\LaravelOidc\Client\Testing\FakeOidcProvider;
use Bambamboole\LaravelOidc\Client\Token\IdTokenValidator;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function jwksRotationClaims(array $overrides = []): array
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

/**
 * @param  array<int, array<int, array<string, mixed>>>  $jwksResponses  Key lists served in order, one per JWKS fetch.
 */
function fakeIssuerWithRotatingJwks(array $jwksResponses): void
{
    $sequence = Http::sequence();
    foreach ($jwksResponses as $keys) {
        $sequence->push(['keys' => $keys]);
    }

    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
        ]),
        'https://id.example.com/.well-known/jwks.json' => $sequence,
    ]);
}

beforeEach(function () {
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'client-123');
});

it('accepts a token signed with a rotated key by refetching the JWKS on an unknown kid', function () {
    $oldProvider = new FakeOidcProvider;
    $newProvider = new FakeOidcProvider;

    fakeIssuerWithRotatingJwks([
        $oldProvider->rsaJwks('key-old'),
        $newProvider->rsaJwks('key-new'),
    ]);

    // Warm the JWKS cache with the pre-rotation key set.
    $validator = app(IdTokenValidator::class);
    $validator->validate($oldProvider->idToken(jwksRotationClaims(), 'key-old'), 'the-nonce');

    // The provider has rotated: key-new is absent from the cached JWKS, so the
    // resolver must refetch before the token can validate.
    $claims = $validator->validate($newProvider->idToken(jwksRotationClaims(), 'key-new'), 'the-nonce');

    expect($claims['sub'])->toBe('42');
    Http::assertSentCount(3); // discovery + cached-miss JWKS fetch + fresh JWKS fetch
});

it('rejects a token whose kid is in neither the cached nor the fresh JWKS', function () {
    $provider = new FakeOidcProvider;

    fakeIssuerWithRotatingJwks([
        $provider->rsaJwks('key-old'),
        $provider->rsaJwks('key-old'),
    ]);

    // Warm the JWKS cache so the resolver's two-pass lookup is exercised.
    app(OidcDiscovery::class)->jwks();

    expect(fn () => app(IdTokenValidator::class)->validate($provider->idToken(jwksRotationClaims(), 'key-unknown'), 'the-nonce'))
        ->toThrow(OidcClientException::class, 'No JWKS key matches');

    Http::assertSentCount(3); // the fresh refetch happened before the rejection
});
