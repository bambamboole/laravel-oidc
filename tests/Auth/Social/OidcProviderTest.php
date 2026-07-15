<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Social\OidcProvider;
use Bambamboole\LaravelOidc\Auth\Social\PendingAuthorization;
use Bambamboole\LaravelOidc\Auth\Social\SocialAuthenticationException;
use Bambamboole\LaravelOidc\Token\Jwk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

function oidcTestProvider(): OidcProvider
{
    return new OidcProvider('corp', [
        'issuer' => 'https://idp.test',
        'client_id' => 'client-1',
        'client_secret' => 'shhh',
    ]);
}

function fakeDiscovery(array $overrides = []): void
{
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response($overrides + [
            'issuer' => 'https://idp.test',
            'authorization_endpoint' => 'https://idp.test/authorize',
            'token_endpoint' => 'https://idp.test/token',
            'jwks_uri' => 'https://idp.test/jwks',
            'userinfo_endpoint' => 'https://idp.test/userinfo',
        ]),
        'https://idp.test/jwks' => Http::response([
            'keys' => [Jwk::fromPem(file_get_contents(__DIR__.'/../../fixtures/oauth-public.key'))],
        ]),
    ]);
}

/**
 * Signs an upstream id_token with the test fixture keypair so JWKS
 * verification runs for real.
 */
function upstreamIdToken(array $claims = [], ?string $nonce = null, string $issuer = 'https://idp.test', string $audience = 'client-1'): string
{
    $config = Configuration::forAsymmetricSigner(
        new Sha256,
        InMemory::file(__DIR__.'/../../fixtures/oauth-private.key'),
        InMemory::file(__DIR__.'/../../fixtures/oauth-public.key'),
    );

    $now = new DateTimeImmutable;
    $builder = $config->builder()
        ->withHeader('kid', Jwk::fromPem(file_get_contents(__DIR__.'/../../fixtures/oauth-public.key'))['kid'])
        ->issuedBy($issuer)
        ->permittedFor($audience)
        ->relatedTo((string) ($claims['sub'] ?? 'upstream-1'))
        ->issuedAt($now)
        ->expiresAt($now->modify('+1 hour'));

    if ($nonce !== null) {
        $builder = $builder->withClaim('nonce', $nonce);
    }

    foreach ($claims as $name => $value) {
        if ($name !== 'sub') {
            $builder = $builder->withClaim($name, $value);
        }
    }

    return $builder->getToken($config->signer(), $config->signingKey())->toString();
}

function oidcCallback(string $idToken): Request
{
    Http::fake([
        'https://idp.test/token' => Http::response([
            'access_token' => 'up-at',
            'refresh_token' => 'up-rt',
            'expires_in' => 3600,
            'id_token' => $idToken,
            'token_type' => 'Bearer',
        ]),
    ]);

    $request = Request::create('/auth/social/corp/callback', 'GET', ['code' => 'code-1', 'state' => 'state-1']);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

it('reads endpoints from the discovery document for the redirect', function () {
    fakeDiscovery();

    $request = Request::create('/auth/social/corp');
    $request->setLaravelSession(app('session.store'));

    $response = oidcTestProvider()->redirect($request);
    parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $params);

    expect($response->getTargetUrl())->toStartWith('https://idp.test/authorize?')
        ->and($params['scope'])->toBe('openid profile email')
        ->and($params['nonce'])->not->toBeEmpty();
});

it('verifies the id_token against the upstream JWKS and returns the user', function () {
    fakeDiscovery();

    $idToken = upstreamIdToken([
        'email' => 'm@example.com',
        'email_verified' => true,
        'name' => 'M',
        'picture' => 'https://idp.test/avatar.png',
    ], nonce: 'nonce-1');

    $pending = new PendingAuthorization('corp', 'login', 'state-1', null, 'nonce-1');
    $user = oidcTestProvider()->user(oidcCallback($idToken), $pending);

    expect($user->id)->toBe('upstream-1')
        ->and($user->email)->toBe('m@example.com')
        ->and($user->emailVerified)->toBeTrue()
        ->and($user->name)->toBe('M')
        ->and($user->avatar)->toBe('https://idp.test/avatar.png')
        ->and($user->accessToken)->toBe('up-at')
        ->and($user->refreshToken)->toBe('up-rt')
        ->and($user->expiresIn)->toBe(3600);
});

it('rejects an id_token with a wrong nonce', function () {
    fakeDiscovery();
    $idToken = upstreamIdToken(nonce: 'other-nonce');

    $pending = new PendingAuthorization('corp', 'login', 'state-1', null, 'nonce-1');
    oidcTestProvider()->user(oidcCallback($idToken), $pending);
})->throws(SocialAuthenticationException::class);

it('rejects an id_token issued to a different audience', function () {
    fakeDiscovery();
    $idToken = upstreamIdToken(nonce: 'nonce-1', audience: 'someone-else');

    $pending = new PendingAuthorization('corp', 'login', 'state-1', null, 'nonce-1');
    oidcTestProvider()->user(oidcCallback($idToken), $pending);
})->throws(SocialAuthenticationException::class);

it('rejects an id_token from a different issuer', function () {
    fakeDiscovery();
    $idToken = upstreamIdToken(nonce: 'nonce-1', issuer: 'https://evil.test');

    $pending = new PendingAuthorization('corp', 'login', 'state-1', null, 'nonce-1');
    oidcTestProvider()->user(oidcCallback($idToken), $pending);
})->throws(SocialAuthenticationException::class);

it('falls back to userinfo for profile claims missing from the id_token', function () {
    fakeDiscovery();
    Http::fake([
        'https://idp.test/userinfo' => Http::response([
            'sub' => 'upstream-1',
            'email' => 'm@example.com',
            'email_verified' => true,
            'name' => 'From Userinfo',
        ]),
    ]);

    $idToken = upstreamIdToken(nonce: 'nonce-1');

    $pending = new PendingAuthorization('corp', 'login', 'state-1', null, 'nonce-1');
    $user = oidcTestProvider()->user(oidcCallback($idToken), $pending);

    expect($user->name)->toBe('From Userinfo')
        ->and($user->email)->toBe('m@example.com');
});
