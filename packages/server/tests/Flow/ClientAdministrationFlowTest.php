<?php

declare(strict_types=1);

/**
 * End-to-end: bootstrap an admin client, administer clients over the API,
 * and prove the administered clients work in the protocol flows.
 */

use Bambamboole\LaravelOidc\Server\Routing\HandlerRegistrar;
use Bambamboole\LaravelOidc\Server\Testing\InteractsWithOidc;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

uses(InteractsWithOidc::class);

beforeEach(function () {
    config(['oidc.admin.enabled' => true, 'oidc.passport.scopes' => ['billing:write' => 'Write billing data']]);
    app(HandlerRegistrar::class)->register();
    Route::getRoutes()->refreshNameLookups();

    Artisan::call('oidc:admin-client', ['--name' => 'Pulumi']);
    $output = Artisan::output();
    preg_match('/OIDC_ADMIN_CLIENT_ID=(\S+)/', $output, $id);
    preg_match('/OIDC_ADMIN_CLIENT_SECRET=(\S+)/', $output, $secret);

    $this->bearer = (string) $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $id[1],
        'client_secret' => $secret[1],
        'scope' => 'oidc:admin',
    ])->assertOk()->json('access_token');

    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'email_verified_at' => now(), 'password' => 'x']);
});

/**
 * @param  array<string, mixed>  $payload
 */
function administeredClient(mixed $test, array $payload): Client
{
    $response = $test->withToken($test->bearer)->postJson('/oauth/admin/clients', $payload)->assertCreated();

    $client = Passport::client()->newQuery()->findOrFail($response->json('client_id'));
    $client->plainSecret = $response->json('client_secret');

    return $client;
}

it('lets an administered client complete the authorization code flow', function () {
    $client = administeredClient($this, [
        'client_name' => 'Orders Web',
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => ['https://rp.test/callback'],
        'scopes' => ['openid', 'email'],
    ]);

    $result = $this->authorizeAndApprove($this->user, $client, 'openid email');

    expect($result->accessToken)->toBeString();

    $this->withToken((string) $result->accessToken)->getJson('/oauth/userinfo')
        ->assertOk()
        ->assertJsonPath('email', 'm@example.com');
});

it('skips consent for an administered trusted client', function () {
    $client = administeredClient($this, [
        'client_name' => 'Orders Web',
        'grant_types' => ['authorization_code'],
        'redirect_uris' => ['https://rp.test/callback'],
        'trusted' => true,
    ]);
    $pkce = $this->pkce();

    $response = $this->actingAsIdentity($this->user, authTime: time() - 60)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://rp.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'st4te',
        'code_challenge' => $pkce->challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://rp.test/callback?')->toContain('code=');
});

it('invalidates issued tokens when a client is revoked over the API', function () {
    $client = administeredClient($this, [
        'client_name' => 'Orders Web',
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => ['https://rp.test/callback'],
    ]);
    $result = $this->authorizeAndApprove($this->user, $client, 'openid');

    $this->withToken((string) $result->accessToken)->getJson('/oauth/userinfo')->assertOk();
    $this->withToken($this->bearer)->deleteJson("/oauth/admin/clients/{$client->getKey()}")->assertNoContent();

    // The guard memoizes its user and Passport memoizes client lookups for the
    // lifetime of the test application; a real request starts from neither.
    app('auth')->forgetGuards();
    Once::flush();

    $this->withToken((string) $result->accessToken)->getJson('/oauth/userinfo')->assertUnauthorized();
    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $result->refreshToken,
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
    ])->assertStatus(401);
});

it('restricts an administered machine client to its allowed scopes', function () {
    $client = administeredClient($this, [
        'client_name' => 'Billing worker',
        'grant_types' => ['client_credentials'],
        'scopes' => ['billing:write'],
    ]);

    $token = fn (string $scope): mixed => parseAccessToken((string) $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'scope' => $scope,
    ])->assertOk()->json('access_token'))->claims()->get('scopes');

    expect($token('billing:write'))->toBe(['billing:write'])
        ->and($token('billing:write openid'))->toBe(['billing:write']);
});
