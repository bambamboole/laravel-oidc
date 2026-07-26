<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\Testing\FakeOidcProvider;
use Bambamboole\LaravelOidc\Client\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in(__DIR__);

/**
 * Stubs the https://id.example.com discovery and JWKS endpoints against the
 * given provider. Pass $keys to serve a custom JWKS key list; the default is
 * the provider's RSA key published under kid `key-1`.
 *
 * @param  array<int, array<string, mixed>>|null  $keys
 */
function fakeIssuerEndpoints(FakeOidcProvider $provider, ?array $keys = null): void
{
    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
        ]),
        'https://id.example.com/.well-known/jwks.json' => Http::response([
            'keys' => $keys ?? $provider->rsaJwks('key-1'),
        ]),
    ]);
}
