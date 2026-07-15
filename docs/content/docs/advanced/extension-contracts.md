---
title: Extension contracts
description: The container-bound seams — ScopeRepository, ClaimsResolver, ExchangePolicy, SessionTokenProvider, DeviceRecognizer — and how to rebind each.
---

Each of the package's extension points is a container-bound interface with a default
implementation. Rebind any of them from a service provider's `register()` (or `boot()`)
method to replace the behaviour without touching a caller.

## `ScopeRepository`

`Bambamboole\LaravelOidc\Contracts\ScopeRepository` is the catalogue of scopes the
provider understands.

```php
interface ScopeRepository
{
    /** @return Collection<int, Scope> */
    public function all(): Collection;

    public function find(string $identifier): ?Scope;

    /**
     * @param  Scope[]  $requested
     * @return Scope[]
     */
    public function finalize(array $requested, string $grantType, ClientEntityInterface $client, ?string $userIdentifier = null): array;
}
```

The default `PassportScopeRepository` merges the OIDC scopes (`openid`, `profile`,
`email`, `address`, `phone`) over `Passport::$scopes`, and applies the wildcard (`*`)
parity rules during `finalize()`. Bind your own to change the catalogue:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\ScopeRepository::class,
    MyScopeRepository::class,
);
```

## `ClaimsResolver`

`Bambamboole\LaravelOidc\Contracts\ClaimsResolver` maps an authenticated user to a
`ClaimSet`.

```php
interface ClaimsResolver
{
    public function resolve(Authenticatable $user): ClaimSet;
}
```

A `ClaimSet` is constructed from a `scope => [claim => value]` map; both the `id_token`
builder and the userinfo endpoint call `forScopes()` on it with the token's granted
scopes, so a claim is only emitted when its scope was granted (null values are dropped).
The default is `DefaultClaimsResolver`. Bind your own:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\ClaimsResolver::class,
    AppClaimsResolver::class,
);
```

## `ExchangePolicy`

`Bambamboole\LaravelOidc\Contracts\ExchangePolicy` authorizes every RFC 8693 token
exchange (and every `Oidc::issueScopedToken()` call).

```php
interface ExchangePolicy
{
    public function authorize(ExchangeRequest $request): ExchangeGrantResult;
}
```

The default `DefaultExchangePolicy` enforces audience reciprocity, the target
allowlist, scope narrowing, same-subject, and a lifetime cap — see
[Token exchange](/provider/token-exchange/) for the full rules. `authorize()` must
return an `ExchangeGrantResult` or throw a
`League\OAuth2\Server\Exception\OAuthServerException`. Rebind it to add tenant checks or
a different allowlist source:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\ExchangePolicy::class,
    TenantScopedExchangePolicy::class,
);
```

## `SessionTokenProvider`

`Bambamboole\LaravelOidc\Contracts\SessionTokenProvider` owns the server-side session
root token used by the [browser-fetch flow](/advanced/browser-fetch/).

```php
interface SessionTokenProvider
{
    public function currentToken(): ?string;

    public function establish(Authenticatable $user): void;

    public function forget(): void;
}
```

The default `SessionMintTokenProvider` mints the root token on the `Login` event,
re-mints it lazily as it nears expiry, and revokes + clears it on the `Logout` event.
Rebind it to source the root token elsewhere (e.g. an external SSO exchange):

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\SessionTokenProvider::class,
    MyExternalSsoTokenProvider::class,
);
```

## `DeviceRecognizer`

`Bambamboole\LaravelOidc\Contracts\DeviceRecognizer` decides whether the current
request comes from a device already known for the user — it backs the
`LoginEvent::isNewDevice()` signal in the post-login pipeline.

```php
interface DeviceRecognizer
{
    public function isKnown(Authenticatable $user, Request $request): bool;
}
```

The default `NullDeviceRecognizer` returns `true` for every request (every device is
treated as known), so `isNewDevice()` is effectively always `false` until a real
device-recognition release ships. There is no device tracking behind it yet. Bind your
own to add it:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\DeviceRecognizer::class,
    MyDeviceRecognizer::class,
);
```
