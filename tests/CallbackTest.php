<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Tests\Support\FakeOidcProvider;
use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;

beforeEach(function () {
    config()->set('oidc-client.enabled', true);
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'client-123');
    config()->set('oidc-client.client_secret', 'secret-xyz');
    config()->set('oidc-client.redirect_uri', 'https://app.test/login/callback');
    config()->set('oidc-client.redirect_after_login', '/dashboard');

    $this->provider = new FakeOidcProvider;
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $claims = [
        'iss' => 'https://id.example.com',
        'aud' => 'client-123',
        'sub' => (string) $this->user->getKey(),
        'nonce' => 'the-nonce',
        'iat' => time(),
        'nbf' => time(),
        'exp' => time() + 300,
    ];

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
        'https://id.example.com/oauth/token' => Http::response([
            'access_token' => 'access-token',
            'id_token' => $this->provider->idToken($claims, 'key-1'),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
    ]);
});

it('completes the callback and logs the user into the web guard', function () {
    $this->withSession([
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ])->get('/login/callback?code=auth-code&state=the-state')
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);

    Http::assertSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code_verifier'] === 'the-verifier'
        && $request['client_secret'] === 'secret-xyz');
});

it('rejects a tampered state and does not log in', function () {
    $this->withSession([
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ])->get('/login/callback?code=auth-code&state=WRONG-state')
        ->assertRedirect(route('login'));

    $this->assertGuest();

    Http::assertNotSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token');
});
