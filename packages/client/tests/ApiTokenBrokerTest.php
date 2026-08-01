<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\ApiTokenBroker;
use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('oidc-client.issuer', 'https://id.example.com');
    config()->set('oidc-client.client_id', 'client-123');

    Http::fake([
        'https://id.example.com/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.example.com',
            'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
            'token_endpoint' => 'https://id.example.com/oauth/token',
            'jwks_uri' => 'https://id.example.com/jwks.json',
        ]),
    ]);

    session()->put('oidc-client.tokens', [
        'access_token' => 'login-token',
        'refresh_token' => 'refresh-token',
        'id_token' => 'id-token',
        'expires_at' => time() + 3600,
    ]);
});

it('exchanges the login token with extension parameters and the issuer as default audience', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::response(['access_token' => 'api-token', 'expires_in' => 300])]);

    expect(app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']))->toBe('api-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token'
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:token-exchange'
        && $request['subject_token'] === 'login-token'
        && $request['subject_token_type'] === 'urn:ietf:params:oauth:token-type:access_token'
        && $request['audience'] === 'https://id.example.com'
        && $request['client_id'] === 'client-123'
        && $request['tenant'] === 'acme');
});

it('strips a trailing slash from the issuer when it is used as the default audience', function () {
    config()->set('oidc-client.issuer', 'https://id.example.com/');
    Http::fake(['https://id.example.com/oauth/token' => Http::response(['access_token' => 'api-token', 'expires_in' => 300])]);

    app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);

    Http::assertSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token'
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:token-exchange'
        && $request['audience'] === 'https://id.example.com');
});

it('uses an explicit audience and caches it separately', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'issuer-token', 'expires_in' => 300])
        ->push(['access_token' => 'other-token', 'expires_in' => 300])]);
    $broker = app(ApiTokenBroker::class);

    expect($broker->accessToken(['tenant' => 'acme']))->toBe('issuer-token')
        ->and($broker->accessToken(['tenant' => 'acme'], 'https://other.example'))->toBe('other-token');

    Http::assertSent(fn ($request) => $request->url() !== 'https://id.example.com/oauth/token'
        || $request['audience'] === 'https://other.example');
});

it('serves a cached token without a second exchange', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::response(['access_token' => 'api-token', 'expires_in' => 300])]);
    $broker = app(ApiTokenBroker::class);

    $broker->accessToken(['tenant' => 'acme']);
    expect($broker->accessToken(['tenant' => 'acme']))->toBe('api-token');

    Http::assertSentCount(2);
});

it('re-exchanges when the cached token is expired', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'api-token', 'expires_in' => 10])
        ->push(['access_token' => 'fresh-token', 'expires_in' => 300])]);
    $broker = app(ApiTokenBroker::class);

    $broker->accessToken(['tenant' => 'acme']);
    expect($broker->accessToken(['tenant' => 'acme']))->toBe('fresh-token');
});

it('caches per parameter set', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'acme-token', 'expires_in' => 300])
        ->push(['access_token' => 'globex-token', 'expires_in' => 300])]);
    $broker = app(ApiTokenBroker::class);

    expect($broker->accessToken(['tenant' => 'acme']))->toBe('acme-token')
        ->and($broker->accessToken(['tenant' => 'globex']))->toBe('globex-token')
        ->and($broker->accessToken(['tenant' => 'acme']))->toBe('acme-token');
});

it('forgets cached tokens', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'api-token', 'expires_in' => 300])
        ->push(['access_token' => 'fresh-token', 'expires_in' => 300])]);
    $broker = app(ApiTokenBroker::class);

    $broker->accessToken(['tenant' => 'acme']);
    $broker->forget();

    expect($broker->accessToken(['tenant' => 'acme']))->toBe('fresh-token');
});

it('throws when the exchange is rejected', function () {
    Http::fake(['https://id.example.com/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);
})->throws(OidcClientException::class);

it('throws when no login token is in the session', function () {
    session()->forget('oidc-client.tokens');

    app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);
})->throws(OidcClientException::class);

it('refreshes an expired login token before exchanging', function () {
    session()->put('oidc-client.tokens.expires_at', time() - 10);
    Http::fake(['https://id.example.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'renewed-login', 'refresh_token' => 'renewed-refresh', 'expires_in' => 3600])
        ->push(['access_token' => 'api-token', 'expires_in' => 300])]);

    expect(app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']))->toBe('api-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token'
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'refresh-token'
        && $request['client_id'] === 'client-123');
    Http::assertSent(fn ($request) => $request->url() === 'https://id.example.com/oauth/token'
        && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:token-exchange'
        && $request['subject_token'] === 'renewed-login');
    expect(session('oidc-client.tokens.access_token'))->toBe('renewed-login')
        ->and(session('oidc-client.tokens.refresh_token'))->toBe('renewed-refresh')
        ->and(session('oidc-client.tokens.expires_at'))->toBeGreaterThan(time() + 3500);
});

it('keeps the old refresh token when the response omits one', function () {
    session()->put('oidc-client.tokens.expires_at', time() - 10);
    Http::fake(['https://id.example.com/oauth/token' => Http::sequence()
        ->push(['access_token' => 'renewed-login', 'expires_in' => 3600])
        ->push(['access_token' => 'api-token', 'expires_in' => 300])]);

    app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);

    expect(session('oidc-client.tokens.refresh_token'))->toBe('refresh-token');
});

it('throws when the refresh is rejected', function () {
    session()->put('oidc-client.tokens.expires_at', time() - 10);
    Http::fake(['https://id.example.com/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);
})->throws(OidcClientException::class);

it('throws when the login token is expired and no refresh token exists', function () {
    session()->put('oidc-client.tokens', ['access_token' => 'login-token', 'expires_at' => time() - 10]);

    app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);
})->throws(OidcClientException::class);
