---
title: Testing
description: OidcClient::fake() — a fake OpenID provider for relying-party tests, with token minting, callback seeding, and flow assertions.
sidebar:
  order: 6
---

`OidcClient::fake()` installs a fake OpenID provider: it stubs the issuer's
discovery, JWKS, token, and end-session endpoints against a test key, resets
the client's resolved services so test config takes effect, and returns an
`OidcClientFake` for minting tokens, seeding the callback session, and asserting
the flow.

The package's routes — including `login` and `login.callback` — register only
when `enabled` is on (see [Configuration](/configuration/)), so a consumer
test environment must set `OIDC_RP_ENABLED=true` or
`config(['oidc-client.enabled' => true])` before `route('login')` or
`callbackUrl()` will resolve.

## Logging a user in

The callback reads a `state`/`nonce`/`code_verifier` triplet from the session.
`callbackContext()` returns it for `withSession()`; `loginAs()` points the
token endpoint's id_token at the user and returns the callback URL:

```php
use Bambamboole\LaravelOidcClient\Facades\OidcClient;

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
see [Back-channel logout](/backchannel-logout/).

## Customizing the provider

- `forUser($user)` — default subject for minted id_tokens
- `issuer($url)` / `clientId($id)` — override the fake issuer or client
- `idToken($claims)` / `logoutToken($claims)` — mint a signed token directly
- `withoutEndSessionEndpoint()` — drop `end_session_endpoint` from discovery
