<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\Testing\FakeOidcProvider;
use Bambamboole\LaravelOidc\Client\Token\IdTokenValidator;
use Bambamboole\LaravelOidc\Client\Token\JwksKeyResolver;
use Bambamboole\LaravelOidc\Client\Token\ValidatorConfig;
use Illuminate\Support\Facades\Http;

it('builds itself from the oidc-client config', function () {
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'client-123');
    config()->set('oidc-client.leeway', 30);

    $config = ValidatorConfig::fromConfig();

    expect($config->issuer)->toBe('https://id.example.com')
        ->and($config->clientId)->toBe('client-123')
        ->and($config->leeway)->toBe(30);
});

it('validates against the injected issuer and client rather than the global config', function () {
    // The discovery/JWKS lookup still follows the globally configured issuer;
    // only the claim expectations come from the injected config.
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'global-client');

    $provider = new FakeOidcProvider;

    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
        ]),
        'https://id.example.com/.well-known/jwks.json' => Http::response([
            'keys' => $provider->rsaJwks('key-1'),
        ]),
    ]);

    $validator = new IdTokenValidator(app(JwksKeyResolver::class), new ValidatorConfig(
        issuer: 'https://second.example.com',
        clientId: 'second-client',
        leeway: 60,
    ));

    $claims = $validator->validate($provider->idToken([
        'iss' => 'https://second.example.com',
        'aud' => 'second-client',
        'sub' => '42',
        'nonce' => 'the-nonce',
        'iat' => time(),
        'nbf' => time(),
        'exp' => time() + 300,
    ], 'key-1'), 'the-nonce');

    expect($claims['iss'])->toBe('https://second.example.com')
        ->and($claims['sub'])->toBe('42');
});
