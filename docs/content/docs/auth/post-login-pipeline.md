---
title: The post-login pipeline
description: The Oidc::postLogin() decision hook that runs once per login, its read/write API, fail-closed behavior, and how it emits acr/amr onto the id_token.
---

`Oidc::postLogin()` is a **decision hook**, not a claim writer. Unlike the
[claim hooks](/provider/claim-hooks/) (`Oidc::onClientCredentials()`, `onTokenExchange()`,
`onUserinfo()`), it participates in the interactive login decision itself. Register it in a service provider's
`boot()`. It runs **exactly once** per login attempt — after the primary factor (password) succeeds
and **before** the [MFA challenge](/auth/multi-factor/) is presented — so it is safe to perform side
effects such as audit logging from inside it.

```php
use Bambamboole\LaravelOidc\Auth\Pipeline\LoginApi;
use Bambamboole\LaravelOidc\Auth\Pipeline\LoginEvent;
use Bambamboole\LaravelOidc\Facades\Oidc;

Oidc::postLogin(function (LoginEvent $event, LoginApi $api): void {
    if ($event->requestsAcr('mfa')) {
        $api->requireMfa();
    }
    $api->setIdTokenClaim('tenant', $event->user->tenant_id);
});
```

Multiple hooks can be registered; they run in registration order, and the pipeline stops at the
first hook that denies the login.

## `LoginEvent` (read side)

`LoginEvent` is read-only and describes the attempt:

| Property / method | Description |
| --- | --- |
| `$event->user` | The `Authenticatable` who just passed the primary factor |
| `$event->client` | The requesting OAuth client (`?ClientEntityInterface`), or `null` outside an authorization request |
| `$event->scopes` | The scopes being requested (`list<string>`) |
| `$event->requestedAcrValues` | The `acr_values` requested by the client (`list<string>`) |
| `$event->ip` | The request's IP address |
| `$event->userAgent` | The request's user agent string |
| `$event->amr` | Authentication methods satisfied so far (`list<string>`, e.g. `['pwd']`) |
| `$event->authTime` | Unix timestamp of authentication, if already known |
| `$event->requestsAcr(string $value): bool` | Whether `$value` is present in `requestedAcrValues` |
| `$event->isNewDevice(): bool` | Device-recognition signal — see limitations below |

## `LoginApi` (write side)

`LoginApi` is used to decide the outcome:

| Method | Effect |
| --- | --- |
| `$api->deny(string $reason)` | Denies the login; the user sees a generic authentication failure |
| `$api->requireMfa()` | Forces the MFA challenge for this login, even if the client didn't request one |
| `$api->setIdTokenClaim(string $name, mixed $value)` | Queues a claim to be added to the `id_token` once the login completes |

`setIdTokenClaim()` refuses protocol-reserved claim names and logs the attempt instead of applying
it. The reserved set is `iss`, `sub`, `aud`, `exp`, `iat`, `nbf`, `jti`, `nonce`, `at_hash`,
`c_hash`, `auth_time`, `azp`, `acr`, and `amr` — so a hook can never forge protocol claims.

## Fail-closed

If a hook throws, the exception is logged and the login is **denied** — it never falls through to a
permissive default. A denied login discards the recorded factor and returns the same generic
`auth.failed` message the user would see for bad credentials.

## `requireMfa()` semantics

Calling `requireMfa()` forces the MFA challenge to be presented. If the user has **no** challengeable
factor enrolled, the login is **denied** rather than silently skipping MFA. When the user does have a
challengeable factor, login defers to the [two-factor challenge](/auth/multi-factor/).

## How this emits `acr` / `amr`

The queued `id_token` claims are stored on the session as the login proceeds, and the satisfied
authentication methods accumulate there too — `pwd` from the password step, plus each verified
factor's method from the MFA challenge. When the `authorization_code` grant issues the `id_token`,
it reads that accumulated set as the `amr` claim and **derives `acr` from it**: `1` for a single
method, `2` when more than one method was satisfied (and no `acr` at all when none were recorded).
Your queued custom claims are merged onto the same `id_token`. Because `acr`/`amr` are
protocol-reserved, they are owned entirely by the OP — the hook influences them only indirectly, by
calling `requireMfa()` (which changes how many methods end up satisfied).

## Current limitations

- Only `setIdTokenClaim()` is available today. `setAccessTokenClaim()`, and reissuing these claims
  on a refreshed token, are planned for a follow-up phase and are not available yet.
- `isNewDevice()` always returns `false` (its recognizer treats every device as known) until the
  device-recognition release ships; there is no real device tracking behind it yet.
