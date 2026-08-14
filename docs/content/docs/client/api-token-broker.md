---
title: API token broker
description: Trading the login session for short-lived, per-audience API tokens via RFC 8693 token exchange.
---

`Bambamboole\LaravelOidc\Client\ApiTokenBroker` turns the login session into short-lived,
per-audience access tokens for calling an API, without the browser or your app code ever handling
a long-lived credential for that API. It is bound as a singleton and resolved via the container.

## Getting an API token

```php
use Bambamboole\LaravelOidc\Client\ApiTokenBroker;

$token = app(ApiTokenBroker::class)->accessToken(['tenant' => 'acme']);
```

`accessToken(array $parameters = [], ?string $audience = null, ?array $scopes = null): string`
exchanges the session's login access token (via
[RFC 8693 token exchange](/provider/token-exchange/)) for a token scoped to `$audience`,
defaulting to `config('oidc-client.issuer')` (with a trailing slash stripped) when omitted.
`$parameters` are sent as extra POST fields on the exchange request — a provider-side
`ExchangePolicy` can read them back as
[extension parameters](/provider/token-exchange/#extension-parameters), e.g. `tenant` above.

`$scopes` narrows the exchanged token: the list is sent space-joined as the `scope` request
parameter, which the provider passes to its `ExchangePolicy` as `requestedScopes`. Omitting it (or
passing an empty list) leaves the scope decision entirely to the provider.

When the caller needs the token's expiry as well — for example to hand `expires_in` on to a
browser client — use `exchangedToken()`, which takes the same arguments and returns an
`ExchangedToken` value object:

```php
$token = app(ApiTokenBroker::class)->exchangedToken(['tenant' => 'acme'], scopes: ['crm:view']);

$token->accessToken; // the exchanged access token
$token->expiresAt;   // unix timestamp
$token->expiresIn(); // remaining seconds, never negative
```

The result is cached in the session under a key derived from the audience, the (sorted) parameter
set, and the (sorted) scope list, so repeated calls with the same arguments reuse the cached token
until it is within 30 seconds of its `expires_in`, rather than exchanging again.

The default audience must equal the provider's issuer identifier — the same value the server
exposes via `oidc.issuer` (`Issuer::url()`) — or exchanges targeting a different audience must be
requested explicitly via `$audience`. Either way, the exchanging client's own
`allowed_exchange_audiences` must include whatever audience is requested, or the token endpoint
rejects the exchange with `invalid_target`; see
[resource servers](/advanced/resource-servers/) for the corresponding server-side configuration.
This is a configuration contract between the two independently-configured packages, not something
either package enforces on your behalf.

Before exchanging, `accessToken()` checks the session's login token
(`oidc-client.tokens.expires_at`, recorded by the [login callback](/client/login-and-logout/)). If
it is missing or within 30 seconds of expiry, it is transparently refreshed via the
`refresh_token` grant first; the renewed tokens are stored back into `oidc-client.tokens`. If the
provider's refresh response omits `refresh_token` or `id_token`, the previous values are kept
rather than dropped.

## Forgetting cached tokens

```php
app(ApiTokenBroker::class)->forget();
```

Clears every cached exchanged token (all audiences and parameter sets) from the session. Call this
on logout alongside clearing the login session itself.

## Concurrency

The server rotates refresh tokens on use, and Laravel's default session driver writes the full
session back at request-terminate without merging concurrent changes. If two requests in the same
session both find the login token expired, both refresh, and the loser's stale session write can
overwrite the winner's freshly-stored tokens with its now-revoked ones — breaking the session until
the next re-login. Guard routes that use the broker with Laravel's session-locking middleware
(`->block()`, or the `block` session middleware) to serialize concurrent requests per session and
avoid this.

## Failures

Every failure mode — no login token, no refresh token when one is needed, the provider rejecting
the refresh, or the provider rejecting the exchange — throws
`Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException`.
