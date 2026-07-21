<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Testing\InteractsWithOidc;
use Workbench\App\Models\User;

uses(InteractsWithOidc::class);

it('renders the consent page for an authorization request', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);
    $pkce = $this->pkce();

    $this->actingAsIdentity($user)
        ->get(route('oidc.authorize', [
            'client_id' => $client->id,
            'redirect_uri' => 'https://rp.test/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => 'st4te',
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => 'S256',
        ]), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee($client->name, false);
});

it('redirects guests to login', function () {
    config(['oidc.login_route' => 'identity.login']);

    $client = $this->createOidcClient('Test RP', ['https://rp.test/callback']);
    $pkce = $this->pkce();

    $this->get(route('oidc.authorize', [
        'client_id' => $client->id,
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'st4te',
        'code_challenge' => $pkce->challenge,
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('identity.login'));
});
