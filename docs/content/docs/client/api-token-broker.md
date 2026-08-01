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

`accessToken(array $parameters = [], ?string $audience = null): string` exchanges the session's
login access token (via [RFC 8693 token exchange](/provider/token-exchange/)) for a token scoped
to `$audience`, defaulting to `config('oidc-client.issuer')` when omitted. `$parameters` are sent
as extra POST fields on the exchange request — a provider-side `ExchangePolicy` can read them back
as [extension parameters](/provider/token-exchange/#extension-parameters), e.g. `tenant` above.

The result is cached in the session under a key derived from the audience and the (sorted)
parameter set, so repeated calls with the same arguments reuse the cached token until it is within
30 seconds of its `expires_in`, rather than exchanging again.

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

## Failures

Every failure mode — no login token, no refresh token when one is needed, the provider rejecting
the refresh, or the provider rejecting the exchange — throws
`Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException`.
