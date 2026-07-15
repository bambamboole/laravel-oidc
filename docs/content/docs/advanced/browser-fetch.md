---
title: Browser-fetch (session tokens)
description: The two-token model that lets a browser call downstream resource servers without ever holding a long-lived, broadly-scoped credential.
---

The package uses a **two-token model** so a browser can call downstream resource
servers without ever holding a long-lived, broadly-scoped credential:

1. A first-party **session root token** — an RFC 9068 access token minted for the
   logged-in user and kept server-side in the Laravel session (never sent to the
   browser). It is established by a [`SessionTokenProvider`](#the-sessiontokenprovider-seam)
   at login and re-minted on demand as it nears expiry.
2. Per-audience **browser tokens** — short-lived, narrowly-scoped access tokens
   exchanged from the root token via [RFC 8693 token exchange](/provider/token-exchange/),
   one per resource server the browser needs to call. These are the only tokens handed
   to the client.

:::caution[Use a server-side session driver]
With `SESSION_DRIVER=cookie` the root token rides inside the encrypted session cookie
sent to the browser. A server-side session driver (e.g. `database`, `redis`) is
recommended so the root token stays server-side.
:::

## Config

| Key | Default | Description |
| --- | --- | --- |
| `oidc.first_party.client_id` | `env('OIDC_FIRST_PARTY_CLIENT')` | The confidential client id used to mint the session root token and to perform exchanges on its behalf. Its `allowed_exchange_audiences` (see [Token exchange](/provider/token-exchange/)) gates which audiences `issueScopedToken()` may mint for. |
| `oidc.session_token.ttl` | `3600` (`OIDC_SESSION_TOKEN_TTL`) | Root token lifetime in seconds. |
| `oidc.session_token.session_key` | `oidc.session_token` | Session key the root token (JWT, `jti`, `expires_at`, `user_id`) is stored under. |
| `oidc.session_token.refresh_skew` | `60` | Seconds before expiry at which `currentToken()` re-mints instead of reusing the stored token. |
| `oidc.session_token.scopes` | `null` | Scopes granted to the root token. `null` grants every non-hidden scope in the `ScopeRepository`; set an array to restrict it. |

## The `SessionTokenProvider` seam

`Bambamboole\LaravelOidc\Contracts\SessionTokenProvider` is the seam that owns the
root token:

```php
interface SessionTokenProvider
{
    public function currentToken(): ?string;

    public function establish(Authenticatable $user): void;

    public function forget(): void;
}
```

It is bound by default to `SessionMintTokenProvider`, which:

- Mints the root token on the `Login` event (via the `EstablishSessionToken` listener),
  revoking any prior root token's `jti` first.
- Re-mints it lazily from `currentToken()` once the stored token is within
  `refresh_skew` seconds of expiry, or when it belongs to a different user.
- Revokes the stored `jti` and clears the session key on the `Logout` event (via the
  `ForgetSessionToken` listener).

Rebind the contract to change *how* the root token is obtained — e.g. sourcing it from
a self-RP or an external SSO exchange — without touching any caller of
`Oidc::issueScopedToken()`:

```php
$this->app->singleton(SessionTokenProvider::class, MyExternalSsoTokenProvider::class);
```

## Issuing a browser token

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

$issued = Oidc::issueScopedToken('https://api.orders.test', ['openid']);
```

`issueScopedToken(string $audience, array $scopes): IssuedToken` reads the current
session root token, exchanges it (in-process, via the same RFC 8693 grant logic used by
`/oauth/token`) for a token scoped to `$audience`, and returns an `IssuedToken`:

```php
final readonly class IssuedToken
{
    public string $accessToken;
    public string $tokenType;   // "Bearer"
    public int $expiresIn;      // seconds remaining until exp
    public string $audience;
    /** @var string[] */
    public array $scopes;
}
```

It throws a `RuntimeException` if there is no session root token for the current user
(`No session token is available for the current user.`), or if
`oidc.first_party.client_id` is unset or does not resolve to a client
(`The oidc.first_party.client_id is not configured or does not exist.`). The usual
[`DefaultExchangePolicy`](/provider/token-exchange/) rules apply — the requested
audience must be in the first-party client's `allowed_exchange_audiences`, and requested
scopes must be a subset of the root token's scopes.

## Validating browser tokens on the resource server

The resource server never sees the session root token — only the audience-scoped
`accessToken` from `IssuedToken`, an RFC 9068 `at+jwt`. See
[Resource servers (CheckAudience)](/advanced/resource-servers/) for the three ways to
validate it: JWKS (stateless), introspection (revocation-aware), or the `CheckAudience`
middleware when the resource server lives in this same Laravel app.
