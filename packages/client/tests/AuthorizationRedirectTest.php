<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->fake = OidcClient::fake()->clientId('client-123');
});

it('redirects to the provider authorization endpoint with pkce', function () {
    $this->fake->assertRedirectedToProvider($this->get(route('login')));

    $this->assertNotNull(session('oidc-client.state'));
    $this->assertNotNull(session('oidc-client.nonce'));
    $this->assertNotNull(session('oidc-client.code_verifier'));
});

it('sends an authenticated user straight home', function () {
    config()->set('oidc-client.redirect_after_login', '/dashboard');

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $this->actingAs($user)->get(route('login'))->assertRedirect('/dashboard');
});
