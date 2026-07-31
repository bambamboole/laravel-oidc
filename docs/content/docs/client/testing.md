---
title: Testing
description: OidcClient::fake() — a fake OpenID provider for relying-party tests, with token minting, callback seeding, and flow assertions.
---

`OidcClient::fake()` installs a fake OpenID provider: it stubs the issuer's
discovery, JWKS, token, and end-session endpoints against a test key, resets
the client's resolved services so test config takes effect, and returns an
`OidcClientFake` for minting tokens, seeding the callback session, and asserting
the flow.

The package's routes — including `login` and `login.callback` — register only
when `enabled` is on (see [Configuration](/client/configuration/)), so a consumer
test environment must set `OIDC_RP_ENABLED=true` or
`config(['oidc-client.enabled' => true])` before `route('login')` or
`callbackUrl()` will resolve.

## Logging a user in

The callback reads a `state`/`nonce`/`code_verifier` triplet from the session.
`callbackContext()` returns it for `withSession()`; `loginAs()` points the
token endpoint's id_token at the user and returns the callback URL:

```php
use Bambamboole\LaravelOidc\Client\Facades\OidcClient;

$fake = OidcClient::fake();

$this->withSession($fake->callbackContext())
    ->get($fake->loginAs($user))
    ->assertRedirect('/dashboard');

$fake->assertLoggedIn($user);
```

`OidcClient::fake()` is a facade fake, so it cannot inject request session
state itself — always pass `callbackContext()` to `withSession()` before
following a callback URL.

## The login redirect

```php
$fake = OidcClient::fake();

$fake->assertRedirectedToProvider($this->get(route('login')));
```

## Failure paths

```php
// Token endpoint returns an error:
$fake = OidcClient::fake()->failTokenExchange();

$this->withSession($fake->callbackContext())
    ->get($fake->callbackUrl())
    ->assertRedirect(route('login'));
$fake->assertCodeExchanged();

// id_token signed by a key absent from the JWKS:
$fake = OidcClient::fake()->withInvalidSignature();

$this->withSession($fake->callbackContext())
    ->get($fake->loginAs($user))
    ->assertRedirect(route('login'));

// Tampered state never reaches the token endpoint:
$fake = OidcClient::fake();
$this->withSession($fake->callbackContext())
    ->get($fake->callbackUrl(['state' => 'WRONG']))
    ->assertRedirect(route('login'));
$fake->assertCodeNotExchanged();
```

## Back-channel logout

```php
$fake = OidcClient::fake();

$this->actingAs($user)->withSession(['oidc-client.sid' => 's1']);

$this->post(route('oidc.backchannel-logout'), [
    'logout_token' => $fake->logoutToken(['sub' => (string) $user->getAuthIdentifier(), 'sid' => 's1']),
])->assertOk();

$fake->assertBackchannelLogoutProcessed('s1');
```

This route only exists when `oidc-client.backchannel_logout.enabled` is on —
see [Back-channel logout](/client/backchannel-logout/).

## Testing self-SSO (provider and relying party in one app)

When the same application runs the server package as its identity provider
and the client package for its own web session, point the client at the app
itself and let the fake stand in for the provider:

```php
config(['oidc-client.issuer' => config('app.url')]);

$fake = OidcClient::fake();

// The login redirect goes through the REAL authorize route — Http::fake()
// only intercepts the relying party's outbound calls (discovery, JWKS,
// token), not requests made through the test kernel:
$fake->assertRedirectedToProvider($this->get(route('login')));

// The callback exchanges the code against the fake token endpoint:
$this->withSession($fake->callbackContext())
    ->get($fake->loginAs($user))
    ->assertRedirect('/dashboard');

$fake->assertLoggedIn($user);
```

`OidcClient::fake()` resets the client's resolved services and discovery/JWKS
caches itself, so `config()->set()` calls in the test take effect without any
`forgetInstance()` bookkeeping. Failure paths (`failTokenExchange()`,
`withInvalidSignature()`, tampered `state`) work the same as against an
external provider.

## Customizing the provider

- `clientId($id)` — override the fake client id
- `idToken($claims)` / `logoutToken($claims)` — mint a signed token directly
- `withoutEndSessionEndpoint()` — drop `end_session_endpoint` from discovery
