---
title: Testing
description: The InteractsWithOidc trait — identity context, real tokens, and the full authorization-code dance in consumer test suites.
---

The package ships test helpers in `Bambamboole\LaravelOidc\Testing`. Add the
trait to your suite:

```php
// Pest (tests/Pest.php)
uses(Bambamboole\LaravelOidc\Testing\InteractsWithOidc::class)->in('Feature');

// PHPUnit
abstract class TestCase extends BaseTestCase
{
    use \Bambamboole\LaravelOidc\Testing\InteractsWithOidc;
}
```

## Authenticating an identity

`actingAsIdentity()` logs the user in on the identity guard and seeds the
session keys the authorization grant reads (`oidc.auth_time`, `oidc.amr`,
`oidc.id_token_claims`, `oidc.access_token_claims`):

```php
$this->actingAsIdentity($user, amr: ['pwd', 'otp'], authTime: time() - 60);
```

There is no `acr` parameter: the grant derives `acr` from `amr`
(`1` for a single method, `2` for multiple).

## Minting tokens without the HTTP dance

`issueTokenFor()` returns a signed `at+jwt` access token with a persisted
Passport token row — ready for a `Bearer` header:

```php
$jwt = $this->issueTokenFor($user, scopes: ['openid', 'email'], audience: ['https://api.orders.test']);

$this->withHeader('Authorization', 'Bearer '.$jwt)->get('/api/orders');
```

When no client is given, a default authorization-code client is created once
per test and reused.

A token minted with a custom `audience:` does not authenticate on plain
`auth:api` routes — Passport resolves the client from `aud[0]`, not from the
audience — it is for routes guarded by the package's audience middleware; see
[Resource servers (CheckAudience)](/advanced/resource-servers/).

## Clients

```php
$client = $this->createOidcClient();                 // auth-code grant client
$client = $this->withFirstPartyClient();             // + sets oidc.first_party.* config
```

Config mutated in a test takes effect immediately — the package reads
`oidc.first_party.*` at call time, so no `forgetInstance()` ceremony is
needed after `config([...])` changes.

`withFirstPartyClient()` sets `oidc.first_party.trusted = true`, so consent
is skipped for that client; register a client via `createOidcClient()`
instead when a test asserts consent behavior.

## The full authorization-code flow

`authorizeAndApprove()` drives authorize → approve → token with PKCE:

```php
$result = $this->authorizeAndApprove($user, $client, scopes: 'openid email');

$result->accessToken;
$result->idToken;
$result->refreshToken;
```

The authorize and approve legs assert success; the token response is returned
unasserted, so error paths stay testable:

```php
$result = $this->authorizeAndApprove($user, $misconfiguredClient);

$result->response->assertStatus(401);
$result->json('error'); // invalid_client
```

`params:` overrides any authorize query parameter (`state`, `nonce`,
`max_age`, `redirect_uri`, ...), and `pkce:` accepts a fixed
`PkcePair` when the test needs the verifier later. The helper registers a
minimal JSON authorization view unless the test already registered one via
`Passport::authorizationView()`.

The CSRF exemption applied by `authorizeAndApprove()` persists for the
remainder of the calling test method.

## Keyless boot

Registering views and actions through the `Oidc` facade in a service
provider's `boot()` requires no `APP_KEY`. Keyless artisan runs —
`package:discover` during `composer install`, fresh-clone setup scripts —
work without workarounds.
