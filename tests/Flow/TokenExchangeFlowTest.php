<?php

declare(strict_types=1);

use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

const EXCHANGE_URN = 'urn:ietf:params:oauth:grant-type:token-exchange';
const ACCESS_TOKEN_URN = 'urn:ietf:params:oauth:token-type:access_token';

beforeEach(function () {
    Passport::tokensCan([
        'openid' => 'Authenticate',
        'orders:read' => 'Read orders',
        'orders:write' => 'Write orders',
    ]);

    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);

    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('RP', ['https://rp.test/cb']);
    $this->client->forceFill([
        'grant_types' => [...(array) $this->client->getAttribute('grant_types'), EXCHANGE_URN],
        'allowed_exchange_audiences' => json_encode(['https://api.internal/orders']),
    ])->save();
    $this->secret = $this->client->plainSecret;
});

it('exchanges a reciprocal token for a narrowed, audience-scoped access token', function () {
    config(['app.url' => 'https://op.test']);
    $subject = mintExchangeSubjectToken((string) $this->client->id, (string) $this->user->id, ['openid', 'orders:read', 'orders:write']);

    $response = $this->post('/oauth/token', [
        'grant_type' => EXCHANGE_URN,
        'client_id' => $this->client->id,
        'client_secret' => $this->secret,
        'subject_token' => $subject,
        'subject_token_type' => ACCESS_TOKEN_URN,
        'audience' => 'https://api.internal/orders',
        'scope' => 'orders:read',
    ])->assertOk();

    expect($response->json('issued_token_type'))->toBe(ACCESS_TOKEN_URN)
        ->and($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('scope'))->toBe('orders:read')
        ->and($response->json())->not->toHaveKey('refresh_token');

    $at = parseAccessToken($response->json('access_token'));
    expect($at->headers()->get('typ'))->toBe('at+jwt')
        ->and($at->claims()->get('aud'))->toBe(['https://api.internal/orders'])
        ->and($at->claims()->get('sub'))->toBe((string) $this->user->id)
        ->and($at->claims()->get('scope'))->toBe('orders:read')
        ->and($at->claims()->get('act'))->toBe(['client_id' => $this->client->id]);
});

it('rejects an unlisted audience with invalid_target', function () {
    $subject = mintExchangeSubjectToken((string) $this->client->id, (string) $this->user->id, ['openid']);
    $this->post('/oauth/token', [
        'grant_type' => EXCHANGE_URN, 'client_id' => $this->client->id, 'client_secret' => $this->secret,
        'subject_token' => $subject, 'subject_token_type' => ACCESS_TOKEN_URN, 'audience' => 'https://evil/api',
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_target');
});

it('rejects an expired subject token with invalid_grant', function () {
    $subject = mintExchangeSubjectToken(
        (string) $this->client->id,
        (string) $this->user->id,
        ['openid'],
        expiresAt: new DateTimeImmutable('-1 hour'),
    );

    $this->post('/oauth/token', [
        'grant_type' => EXCHANGE_URN,
        'client_id' => $this->client->id,
        'client_secret' => $this->secret,
        'subject_token' => $subject,
        'subject_token_type' => ACCESS_TOKEN_URN,
        'audience' => 'https://api.internal/orders',
    ])->assertStatus(400)
        ->assertJsonPath('error', 'invalid_grant')
        ->assertJsonMissingPath('access_token');
});

it('rejects a revoked subject token with invalid_grant', function () {
    $subject = mintExchangeSubjectToken((string) $this->client->id, (string) $this->user->id, ['openid'], revoked: true);

    $this->post('/oauth/token', [
        'grant_type' => EXCHANGE_URN,
        'client_id' => $this->client->id,
        'client_secret' => $this->secret,
        'subject_token' => $subject,
        'subject_token_type' => ACCESS_TOKEN_URN,
        'audience' => 'https://api.internal/orders',
    ])->assertStatus(400)
        ->assertJsonPath('error', 'invalid_grant')
        ->assertJsonMissingPath('access_token');
});

it('rejects a client without the grant', function () {
    $other = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Other', ['https://o/cb']);
    $subject = mintExchangeSubjectToken((string) $this->client->id, (string) $this->user->id, ['openid']);
    $this->post('/oauth/token', [
        'grant_type' => EXCHANGE_URN, 'client_id' => $other->id, 'client_secret' => $other->plainSecret,
        'subject_token' => $subject, 'subject_token_type' => ACCESS_TOKEN_URN, 'audience' => 'https://api.internal/orders',
    ])->assertStatus(400);
});

it('advertises the grant in discovery when enabled', function () {
    expect($this->getJson('/.well-known/openid-configuration')->json('grant_types_supported'))
        ->toContain(EXCHANGE_URN);
});

it('omits the grant from discovery when disabled', function () {
    config(['oidc.token_exchange.enabled' => false]);

    expect($this->getJson('/.well-known/openid-configuration')->json('grant_types_supported'))
        ->not->toContain(EXCHANGE_URN);
});
