---
title: Extension contracts
description: The container-bound seams — IssuerResolver, ScopeRepository, ClaimsResolver, ExchangePolicy, SessionTokenProvider, DeviceRecognizer — and how to rebind each.
---

Each of the package's extension points is a container-bound interface with a default
implementation. Rebind any of them from a service provider's `register()` (or `boot()`)
method to replace the behavior without touching a caller.

## `IssuerResolver`

`Bambamboole\LaravelOidc\Server\Realms\IssuerResolver` returns the issuer identifier every
protocol surface builds on: the `iss` claim of `id_token`s, access tokens and logout tokens, the
`issuer` and endpoint URLs in the discovery document, the audience the `oidc` guard accepts, and
RFC 9728 resource metadata.

```php
interface IssuerResolver
{
    public function url(): string;
}
```

The default `ConfiguredIssuerResolver` returns `oidc.issuer`, falling back to `app.url`, with any
trailing slash trimmed. It is bound as a **scoped** binding, so a resolver may derive the issuer
from the current request (a host or path segment) and still be reset per request under Octane.

```php
$this->app->scoped(
    \Bambamboole\LaravelOidc\Server\Realms\IssuerResolver::class,
    PerHostIssuerResolver::class,
);
```

Every issuer URL the package emits or validates against goes through this contract, so a rebind
changes them consistently. It does not move any route — endpoint paths still come from the
[routes file](/introduction/route-handlers/), and the discovery document composes them onto the
resolved issuer origin.

## `ScopeRepository`

`Bambamboole\LaravelOidc\Server\Scopes\ScopeRepository` is the catalog of scopes the
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

The default `DefaultScopeRepository` merges scopes in order: first, the configured
catalog (`oidc.scopes.catalog`); second, the built-in OIDC scopes (`openid`, `profile`, `email`, `address`, `phone`).
The first occurrence of a scope id wins. Its `finalize()` filters out unknown scopes.
(See [Scopes & claims](/provider/scopes-and-claims/) for a deeper look at the merge strategy.) Bind your own to change
the catalog:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Server\Scopes\ScopeRepository::class,
    MyScopeRepository::class,
);
```

## `ClaimsResolver`

`Bambamboole\LaravelOidc\Server\Scopes\Claims\ClaimsResolver` turns a `ClaimsRequest` into the
claims to emit.

```php
interface ClaimsResolver
{
    /** @return array<string, mixed> */
    public function resolve(ClaimsRequest $request): array;
}
```

The request carries the user, the requesting client (`clientId`), the granted `scopes`, and
the `audience` being built (`ClaimsAudience::IdToken` or `::Userinfo`), so a resolver can vary
claims per client and per surface. `ClaimSet` remains available for the common scope-gated
case — see [Scopes & claims](/provider/scopes-and-claims/) for both shapes.
The default is `DefaultClaimsResolver`. Bind your own:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Server\Scopes\Claims\ClaimsResolver::class,
    AppClaimsResolver::class,
);
```

## `ExchangePolicy`

`Bambamboole\LaravelOidc\Server\Tokens\Exchange\ExchangePolicy` authorizes every RFC 8693 token
exchange (and every `IssueScopedToken` action call).

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
    \Bambamboole\LaravelOidc\Server\Tokens\Exchange\ExchangePolicy::class,
    TenantScopedExchangePolicy::class,
);
```

## `SessionTokenProvider`

`Bambamboole\LaravelOidc\Server\Sessions\SessionTokenProvider` owns the server-side session
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
    \Bambamboole\LaravelOidc\Server\Sessions\SessionTokenProvider::class,
    MyExternalSsoTokenProvider::class,
);
```

## `DeviceRecognizer`

`Bambamboole\LaravelOidc\Server\Authentication\Pipeline\Contracts\DeviceRecognizer` decides whether the current
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
    \Bambamboole\LaravelOidc\Server\Authentication\Pipeline\Contracts\DeviceRecognizer::class,
    MyDeviceRecognizer::class,
);
```
