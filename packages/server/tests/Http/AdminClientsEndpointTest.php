<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Audit\AuditEvent;
use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Bambamboole\LaravelOidc\Server\Routing\Handler;
use Bambamboole\LaravelOidc\Server\Routing\HandlerRegistrar;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;

function enableClientAdministration(): void
{
    config(['oidc.admin.enabled' => true]);

    app(HandlerRegistrar::class)->register();
    Route::getRoutes()->refreshNameLookups();
}

function administrationClient(string $name = 'IaC runner'): Client
{
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient($name);
    $client->forceFill(['scopes' => ['oidc:admin']])->save();

    return $client;
}

function administrationBearer(mixed $test, Client $client, string $scope = 'oidc:admin'): string
{
    return (string) $test->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'scope' => $scope,
    ])->assertOk()->json('access_token');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientPayload(array $overrides = []): array
{
    return [
        'client_name' => 'Orders Web',
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => ['https://orders.test/callback'],
        ...$overrides,
    ];
}

beforeEach(function () {
    enableClientAdministration();

    $this->admin = administrationClient();
    $this->bearer = administrationBearer($this, $this->admin);
});

function asAdmin(mixed $test): mixed
{
    return $test->withToken($test->bearer);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return TestResponse<Response>
 */
function createClient(mixed $test, array $overrides = []): TestResponse
{
    return asAdmin($test)->postJson('/oauth/admin/clients', clientPayload($overrides))->assertCreated();
}

it('does not register the endpoints while administration is disabled', function () {
    config(['oidc.admin.enabled' => false]);

    foreach (Handler::cases() as $handler) {
        if ($handler->isAdmin()) {
            expect($handler->config())->toBeFalse();
        }
    }
});

it('throttles every administration endpoint', function () {
    foreach (Handler::cases() as $handler) {
        if ($handler->isAdmin()) {
            expect(Route::getRoutes()->getByName($handler->value)?->middleware())->toContain('throttle');
        }
    }
});

it('rejects requests without an admin token', function () {
    $this->getJson('/oauth/admin/clients')->assertUnauthorized()->assertJsonPath('error', 'invalid_token');

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $userToken = mintExchangeSubjectToken((string) $this->admin->getKey(), (string) $user->getKey(), ['oidc:admin']);

    $this->withToken($userToken)->getJson('/oauth/admin/clients')->assertUnauthorized();
    $this->withToken(administrationBearer($this, $this->admin, scope: ''))->getJson('/oauth/admin/clients')->assertForbidden();
});

it('creates a confidential client and returns the secret once', function () {
    $response = createClient($this);
    $clientId = $response->json('client_id');
    $client = Passport::client()->newQuery()->findOrFail($clientId);

    $response->assertJson([
        'client_id' => $clientId,
        'client_name' => 'Orders Web',
        'confidential' => true,
        'grant_types' => ['authorization_code', 'refresh_token'],
        'redirect_uris' => ['https://orders.test/callback'],
        'post_logout_redirect_uris' => [],
        'backchannel_logout_uri' => null,
        'backchannel_logout_session_required' => false,
        'scopes' => null,
        'allowed_exchange_audiences' => [],
        'trusted' => false,
    ]);

    expect(array_keys($response->json()))->toBe([
        'client_id', 'client_secret', 'client_name', 'confidential', 'grant_types', 'redirect_uris',
        'post_logout_redirect_uris', 'backchannel_logout_uri', 'backchannel_logout_session_required', 'scopes',
        'allowed_exchange_audiences', 'trusted', 'created_at', 'updated_at',
    ])
        ->and(Hash::check($response->json('client_secret'), (string) $client->getRawOriginal('secret')))->toBeTrue()
        ->and($response->json('created_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/')
        ->and(asAdmin($this)->getJson("/oauth/admin/clients/{$clientId}")->assertOk()->json())->not->toHaveKey('client_secret');
});

it('echoes normalized metadata identically on create and read', function () {
    $response = createClient($this, [
        'redirect_uris' => [' https://orders.test/callback ', 'https://orders.test/callback', 'https://orders.test/other'],
        'post_logout_redirect_uris' => ['https://orders.test/'],
        'backchannel_logout_uri' => 'https://orders.test/backchannel',
        'backchannel_logout_session_required' => true,
        'allowed_exchange_audiences' => ['https://api.orders.test'],
        'grant_types' => ['authorization_code', 'refresh_token', 'urn:ietf:params:oauth:grant-type:token-exchange'],
        'scopes' => ['openid', 'email', 'openid'],
        'trusted' => true,
    ]);

    $created = $response->collect()->except('client_secret')->all();
    $read = asAdmin($this)->getJson('/oauth/admin/clients/'.$response->json('client_id'))->assertOk()->json();

    expect($created['redirect_uris'])->toBe(['https://orders.test/callback', 'https://orders.test/other'])
        ->and($created['scopes'])->toBe(['openid', 'email'])
        ->and($created['trusted'])->toBeTrue()
        ->and($read)->toBe($created);
});

it('distinguishes omitted, null and empty scopes', function () {
    expect(createClient($this)->json('scopes'))->toBeNull()
        ->and(createClient($this, ['scopes' => null])->json('scopes'))->toBeNull()
        ->and(createClient($this, ['scopes' => []])->json('scopes'))->toBe([]);
});

it('rejects invalid input with field errors', function (array $overrides, string $field) {
    asAdmin($this)->postJson('/oauth/admin/clients', clientPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['client_name' => null], 'client_name'],
    'long name' => [['client_name' => str_repeat('a', 256)], 'client_name'],
    'empty grant types' => [['grant_types' => []], 'grant_types'],
    'unsupported grant type' => [['grant_types' => ['password']], 'grant_types'],
    'code without redirect uri' => [['redirect_uris' => []], 'redirect_uris'],
    'redirect uri with fragment' => [['redirect_uris' => ['https://orders.test/cb#frag']], 'redirect_uris'],
    'relative back-channel uri' => [['backchannel_logout_uri' => '/logout'], 'backchannel_logout_uri'],
    'unknown scope' => [['scopes' => ['nope']], 'scopes'],
    'non-boolean trusted' => [['trusted' => 'yes'], 'trusted'],
    'unknown field' => [['token_endpoint_auth_method' => 'none'], 'token_endpoint_auth_method'],
    'client secret in body' => [['client_secret' => 'mine'], 'client_secret'],
]);

it('rejects a body that is not a JSON object', function () {
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->bearer];

    $this->call('POST', '/oauth/admin/clients', [], [], [], $server, '{not json')
        ->assertStatus(400)
        ->assertJsonPath('message', 'The request body must be a JSON object.');

    $this->call('POST', '/oauth/admin/clients', [], [], [], $server, '["a"]')
        ->assertStatus(400);
});

it('ignores read-only fields so a fetched representation can be sent back', function () {
    $created = createClient($this)->json();
    $read = asAdmin($this)->getJson("/oauth/admin/clients/{$created['client_id']}")->json();

    asAdmin($this)->patchJson("/oauth/admin/clients/{$created['client_id']}", [...$read, 'client_name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('client_name', 'Renamed')
        ->assertJsonPath('client_id', $created['client_id']);
});

it('patches only the fields present in the body', function () {
    $clientId = createClient($this, ['scopes' => ['openid'], 'trusted' => true])->json('client_id');

    asAdmin($this)->patchJson("/oauth/admin/clients/{$clientId}", ['client_name' => 'Orders Web v2'])
        ->assertOk()
        ->assertJsonPath('client_name', 'Orders Web v2')
        ->assertJsonPath('scopes', ['openid'])
        ->assertJsonPath('trusted', true);

    asAdmin($this)->patchJson("/oauth/admin/clients/{$clientId}", [])
        ->assertOk()
        ->assertJsonPath('client_name', 'Orders Web v2');

    asAdmin($this)->patchJson("/oauth/admin/clients/{$clientId}", ['scopes' => null, 'trusted' => false])
        ->assertOk()
        ->assertJsonPath('scopes', null)
        ->assertJsonPath('trusted', false);
});

it('keeps dynamically registered public clients public', function () {
    $public = app(ClientRepository::class)->createAuthorizationCodeGrantClient('MCP', ['https://mcp.test/cb'], confidential: false);
    $path = "/oauth/admin/clients/{$public->getKey()}";

    asAdmin($this)->getJson($path)->assertOk()->assertJsonPath('confidential', false);
    asAdmin($this)->patchJson($path, ['client_name' => 'MCP client'])->assertOk()->assertJsonPath('confidential', false);
    asAdmin($this)->patchJson($path, ['trusted' => true])->assertUnprocessable()->assertJsonValidationErrors('trusted');
    asAdmin($this)->patchJson($path, ['grant_types' => ['client_credentials']])->assertUnprocessable()->assertJsonValidationErrors('grant_types');
    asAdmin($this)->postJson("{$path}/secret")->assertStatus(409);
});

it('answers 404 for unknown, revoked and user-owned clients', function () {
    $owned = Passport::client()->forceFill([
        'name' => 'Personal', 'secret' => 'x', 'redirect_uris' => ['https://rp.test/cb'], 'grant_types' => ['authorization_code'],
        'revoked' => false, 'owner_id' => 'user-1', 'owner_type' => 'users',
    ]);
    $owned->save();

    $revokedId = createClient($this)->json('client_id');
    asAdmin($this)->deleteJson("/oauth/admin/clients/{$revokedId}")->assertNoContent();

    foreach (['missing', $owned->getKey(), $revokedId] as $id) {
        asAdmin($this)->getJson("/oauth/admin/clients/{$id}")->assertNotFound();
        asAdmin($this)->patchJson("/oauth/admin/clients/{$id}", ['client_name' => 'x'])->assertNotFound();
        asAdmin($this)->postJson("/oauth/admin/clients/{$id}/secret")->assertNotFound();
        asAdmin($this)->deleteJson("/oauth/admin/clients/{$id}")->assertNotFound();
    }
});

it('rotates the secret and returns it once', function () {
    $created = createClient($this, ['grant_types' => ['client_credentials']])->json();
    $rotated = asAdmin($this)->postJson("/oauth/admin/clients/{$created['client_id']}/secret")->assertOk()->json();

    expect($rotated['client_secret'])->not->toBe($created['client_secret']);

    $token = fn (string $secret): TestResponse => $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $created['client_id'],
        'client_secret' => $secret,
        'scope' => '',
    ]);

    $token($rotated['client_secret'])->assertOk();
    $token($created['client_secret'])->assertStatus(401);
});

it('revokes a client together with its tokens', function () {
    $other = administrationClient('Other runner');
    $otherBearer = administrationBearer($this, $other);

    asAdmin($this)->deleteJson("/oauth/admin/clients/{$other->getKey()}")->assertNoContent();

    $this->withToken($otherBearer)->getJson('/oauth/admin/clients')->assertUnauthorized();
    asAdmin($this)->getJson("/oauth/admin/clients/{$other->getKey()}")->assertNotFound();
    expect(asAdmin($this)->getJson('/oauth/admin/clients')->collect('data')->pluck('client_id'))->not->toContain((string) $other->getKey());
});

it('refuses self revocation and changes to managed clients', function () {
    asAdmin($this)->deleteJson("/oauth/admin/clients/{$this->admin->getKey()}")->assertStatus(409);
    asAdmin($this)->getJson('/oauth/admin/clients')->assertOk();

    $managedId = createClient($this)->json('client_id');
    Passport::client()->newQuery()->whereKey($managedId)->update(['oidc_provisioning_key' => 'first-party']);

    asAdmin($this)->patchJson("/oauth/admin/clients/{$managedId}", ['client_name' => 'x'])->assertStatus(409);
    asAdmin($this)->deleteJson("/oauth/admin/clients/{$managedId}")->assertStatus(409);
    asAdmin($this)->postJson("/oauth/admin/clients/{$managedId}/secret")->assertOk();
});

it('lists clients with cursor pagination', function () {
    $ids = [(string) $this->admin->getKey()];

    foreach (['A', 'B', 'C'] as $name) {
        $ids[] = createClient($this, ['client_name' => $name])->json('client_id');
    }

    $first = asAdmin($this)->getJson('/oauth/admin/clients?per_page=2')->assertOk();
    $second = asAdmin($this)->getJson('/oauth/admin/clients?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertOk();

    $firstPage = $first->collect('data')->pluck('client_id');
    $secondPage = $second->collect('data')->pluck('client_id');

    expect($firstPage)->toHaveCount(2)
        ->and($secondPage)->toHaveCount(2)
        ->and($firstPage->merge($secondPage)->sort()->values()->all())->toBe(collect($ids)->sort()->values()->all())
        ->and($second->json('meta.next_cursor'))->toBeNull()
        ->and($first->json('data.1'))->not->toHaveKey('client_secret');

    asAdmin($this)->getJson('/oauth/admin/clients?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('audits administrative changes with the acting client', function () {
    $sink = fakeAudit();
    $actor = (string) $this->admin->getKey();

    $clientId = createClient($this)->json('client_id');
    asAdmin($this)->patchJson("/oauth/admin/clients/{$clientId}", ['client_name' => 'Renamed'])->assertOk();
    asAdmin($this)->postJson("/oauth/admin/clients/{$clientId}/secret")->assertOk();
    asAdmin($this)->deleteJson("/oauth/admin/clients/{$clientId}")->assertNoContent();

    foreach ([AuditEventType::ClientCreated, AuditEventType::ClientUpdated, AuditEventType::ClientSecretRotated, AuditEventType::ClientRevoked] as $type) {
        $sink->assertRecorded($type, fn (AuditEvent $event): bool => $event->clientId === $clientId && $event->context['actor'] === $actor);
    }
});
