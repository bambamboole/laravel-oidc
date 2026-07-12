<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Workbench\App\Models\User;

beforeEach(function () {
    config()->set('oidc-client.enabled', true);
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'client-123');
    config()->set('oidc-client.redirect_uri', 'https://app.test/login/callback');

    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
        ]),
    ]);
});

it('redirects to the provider authorization endpoint with pkce', function () {
    $response = $this->get(route('login'));

    $response->assertRedirectContains('https://id.example.com/oauth/authorize');
    $response->assertRedirectContains('response_type=code');
    $response->assertRedirectContains('code_challenge_method=S256');
    $response->assertRedirectContains('client_id=client-123');

    $this->assertNotNull(session('oidc-client.state'));
    $this->assertNotNull(session('oidc-client.nonce'));
    $this->assertNotNull(session('oidc-client.code_verifier'));
});

it('sends an authenticated user straight home', function () {
    config()->set('oidc-client.redirect_after_login', '/dashboard');

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->get(route('login'))->assertRedirect('/dashboard');
});
