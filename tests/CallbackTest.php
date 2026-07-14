<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Tests\Support\FakeOidcProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;

beforeEach(function () {
    Auth::forgetGuards();
    Cache::clear();
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

it('rejects missing or empty callback session context before discovery or token exchange', function (array $context) {
    $this->withSession($context)
        ->get('/login/callback?code=auth-code&state=the-state')
        ->assertRedirect(route('login'));

    $this->assertGuest();
    Http::assertNotSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token');
})->with([
    'missing context' => [[]],
    'missing state' => [[
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ]],
    'empty state' => [[
        'oidc-client.state' => '',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ]],
    'missing nonce' => [[
        'oidc-client.state' => 'the-state',
        'oidc-client.code_verifier' => 'the-verifier',
    ]],
    'empty nonce' => [[
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => '',
        'oidc-client.code_verifier' => 'the-verifier',
    ]],
    'missing verifier' => [[
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
    ]],
    'empty verifier' => [[
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => '',
    ]],
]);

it('rejects replayed callback session context before discovery or token exchange', function () {
    $this->withSession([
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ])->get('/login/callback?code=auth-code&state=WRONG-state')
        ->assertRedirect(route('login'));

    Http::fake();

    $this->get('/login/callback?code=auth-code&state=the-state')
        ->assertRedirect(route('login'));

    $this->assertGuest();
    Http::assertNotSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token');
});

it('rejects a failed token exchange and does not log in', function () {
    config()->set('oidc-client.issuer', 'https://exchange-failure.example.com');

    Http::fake([
        'https://exchange-failure.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://exchange-failure.example.com',
            'authorization_endpoint' => 'https://exchange-failure.example.com/oauth/authorize',
            'token_endpoint' => 'https://exchange-failure.example.com/oauth/token',
            'jwks_uri' => 'https://exchange-failure.example.com/.well-known/jwks.json',
        ]),
        'https://exchange-failure.example.com/oauth/token' => Http::response([], 400),
    ]);

    $this->withSession([
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ])->get('/login/callback?code=auth-code&state=the-state')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects an id token with a tampered signature and does not log in', function () {
    config()->set('oidc-client.issuer', 'https://tampered.example.com');

    $otherProvider = new FakeOidcProvider;
    $claims = [
        'iss' => 'https://tampered.example.com',
        'aud' => 'client-123',
        'sub' => (string) $this->user->getKey(),
        'nonce' => 'the-nonce',
        'iat' => time(),
        'exp' => time() + 300,
    ];

    Http::fake([
        'https://tampered.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://tampered.example.com',
            'authorization_endpoint' => 'https://tampered.example.com/oauth/authorize',
            'token_endpoint' => 'https://tampered.example.com/oauth/token',
            'jwks_uri' => 'https://tampered.example.com/.well-known/jwks.json',
        ]),
        'https://tampered.example.com/.well-known/jwks.json' => Http::response([
            'keys' => $this->provider->rsaJwks('key-1'),
        ]),
        'https://tampered.example.com/oauth/token' => Http::response([
            'id_token' => $otherProvider->idToken($claims, 'key-1'),
        ]),
    ]);

    $this->withSession([
        'oidc-client.state' => 'the-state',
        'oidc-client.nonce' => 'the-nonce',
        'oidc-client.code_verifier' => 'the-verifier',
    ])->get('/login/callback?code=auth-code&state=the-state')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
