<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Http\Middleware\AuthenticateAdminClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Workbench\App\Models\User;

beforeEach(function () {
    config(['oidc.admin.enabled' => true]);

    Route::middleware(AuthenticateAdminClient::class)->get('/admin-probe', fn (Request $request) => [
        'actor' => $request->attributes->get(AuthenticateAdminClient::ClientIdAttribute),
    ]);

    $this->client = app(ClientRepository::class)->createClientCredentialsGrantClient('IaC runner');
    $this->client->forceFill(['scopes' => ['oidc:admin']])->save();
});

function adminBearer(mixed $test, Client $client, string $scope = 'oidc:admin'): string
{
    return (string) $test->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'scope' => $scope,
    ])->assertOk()->json('access_token');
}

it('admits a client credentials token carrying the admin scope and exposes the actor', function () {
    $this->withToken(adminBearer($this, $this->client))
        ->getJson('/admin-probe')
        ->assertOk()
        ->assertJsonPath('actor', (string) $this->client->getKey());
});

it('rejects a missing or malformed bearer as invalid_token', function (?string $bearer) {
    $request = $bearer === null ? $this : $this->withToken($bearer);

    $request->getJson('/admin-probe')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'invalid_token')
        ->assertHeader('WWW-Authenticate', 'Bearer realm="OIDC", error="invalid_token"');
})->with([null, 'not-a-jwt']);

it('rejects a user-bound token even when it carries the admin scope', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $bearer = mintExchangeSubjectToken((string) $this->client->getKey(), (string) $user->getKey(), ['oidc:admin']);

    $this->withToken($bearer)->getJson('/admin-probe')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
});

it('rejects an expired or revoked token', function (array $options) {
    $bearer = mintExchangeSubjectToken((string) $this->client->getKey(), 'unused', ['oidc:admin'], ...[...$options, 'userless' => true]);

    $this->withToken($bearer)->getJson('/admin-probe')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
})->with([
    'expired' => [['expiresAt' => new DateTimeImmutable('-1 minute')]],
    'revoked' => [['revoked' => true]],
]);

it('rejects a token without the admin scope as insufficient_scope', function () {
    $this->withToken(adminBearer($this, $this->client, scope: ''))
        ->getJson('/admin-probe')
        ->assertForbidden()
        ->assertJsonPath('error', 'insufficient_scope')
        ->assertHeader('WWW-Authenticate', 'Bearer realm="OIDC", error="insufficient_scope"');
});

it('never accepts the wildcard scope in place of the admin scope', function () {
    $bearer = mintExchangeSubjectToken((string) $this->client->getKey(), 'unused', ['*'], userless: true);

    $this->withToken($bearer)->getJson('/admin-probe')->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
});

it('rejects a token once its client no longer lists the admin scope', function () {
    $bearer = adminBearer($this, $this->client);
    $this->client->forceFill(['scopes' => []])->save();

    $this->withToken($bearer)->getJson('/admin-probe')->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
});

it('rejects a token once its client is revoked', function () {
    $bearer = adminBearer($this, $this->client);
    app(ClientRepository::class)->delete($this->client);

    $this->withToken($bearer)->getJson('/admin-probe')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
});

it('renders later errors as JSON regardless of the accept header', function () {
    Route::middleware(AuthenticateAdminClient::class)->get('/admin-missing', fn () => abort(404, 'Nope'));

    $this->withToken(adminBearer($this, $this->client))
        ->get('/admin-missing', ['Accept' => 'text/html'])
        ->assertNotFound()
        ->assertJsonPath('message', 'Nope');
});

it('rejects a token addressed to another resource', function () {
    $this->client->forceFill(['allowed_exchange_audiences' => json_encode(['https://api.test'])])->save();

    $bearer = (string) $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $this->client->getKey(),
        'client_secret' => $this->client->plainSecret,
        'scope' => 'oidc:admin',
        'resource' => 'https://api.test',
    ])->assertOk()->json('access_token');

    expect(parseAccessToken($bearer)->claims()->get('aud'))->toBe(['https://api.test']);

    $this->withToken($bearer)->getJson('/admin-probe')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
});
