---
title: Scopes & claims
description: The OIDC scope catalog and how an authenticated user is mapped to claims.
---

## Scope catalog

The provider understands the OIDC standard scopes — `openid`, `profile`, `email`, `address`,
`phone` — merged with your configured catalog (`scopes.catalog`, below). On a conflict the configured
catalog wins over the built-in OIDC scopes — so you can
override the description of a standard scope simply by defining it in your catalog.

### Wildcard (`*`)

`*` always resolves as a scope and survives finalization for the `personal_access` and
`client_credentials` grants (e.g. `$user->createToken('cli', ['*'])`). It is stripped for
`authorization_code` (interactive) flows, where a blanket grant has no business being
granted on a consent screen.

### Registering API scopes

Feed your API scope catalog to the provider through `config/oidc.php`'s `scopes.catalog`
option:

```php
'scopes' => [
    'catalog' => App\Auth\ApiScopes::class,   // or an inline [scope => description] map
],
```

A class-string must implement `Bambamboole\LaravelOidc\Server\Shared\Scopes\ScopeCatalog`
(`scopes(): array<string, string>`). The scope repository consults it lazily —
resolved from the container the first time scopes are actually enumerated (the
consent screen, the discovery document, token issuance), so a database-backed
catalog costs nothing on unrelated requests, keeping key- and db-less artisan
runs working, and the result is memoized for the life of the repository; an
inline array is read fresh on every enumeration.
Exceptions thrown by `scopes()` fall back to an empty catalog; an invalid
class-string still fails loudly, at first enumeration rather than at boot.
Enumerate the full catalog through the `ScopeRepository` contract.

The scope catalog is provided by the `ScopeRepository` contract — see
[Extension contracts](/advanced/extension-contracts/) to swap it.

## Claims

`Bambamboole\LaravelOidc\Server\Scopes\Contracts\ClaimsResolver` turns a `ClaimsRequest` into the claim
map to emit. The request carries the authenticated user, the requesting client, the granted
scopes, and which surface is being built — so a resolver can vary claims per client, or emit a
claim into the `id_token` but not userinfo.

```php
final readonly class ClaimsRequest
{
    public Authenticatable $user;
    public ClaimsAudience $audience;   // IdToken | Userinfo
    public ?string $clientId;
    /** @var list<string> */
    public array $scopes;

    public function hasScope(string $scope): bool;
}
```

`ClaimSet` remains available for the common scope-gated case: construct it from a
`scope => [claim => value]` map and call `forScopes()` with the request's scopes, so a claim is
only emitted when its scope was granted — null values are dropped.

```php
use Bambamboole\LaravelOidc\Server\Scopes\Claims\ClaimSet;
use Bambamboole\LaravelOidc\Server\Scopes\Claims\ClaimsRequest;
use Bambamboole\LaravelOidc\Server\Scopes\Contracts\ClaimsResolver;

class AppClaimsResolver implements ClaimsResolver
{
    public function resolve(ClaimsRequest $request): array
    {
        $user = $request->user;

        return (new ClaimSet([
            'profile' => ['name' => $user->name],
            'email' => [
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]))->forScopes($request->scopes);
    }
}
```

A resolver is free to ignore scopes entirely and key off the client or the audience instead:

```php
public function resolve(ClaimsRequest $request): array
{
    if ($request->audience !== ClaimsAudience::IdToken) {
        return [];
    }

    return ['tenant' => $this->tenantFor($request->clientId)];
}
```

`clientId` is null only where the caller cannot attribute the request to a client. Protocol claims
the caller owns — `sub`, `iss`, `aud`, and friends — are ignored if a resolver returns them.

Bind your resolver so the provider uses it:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Server\Scopes\Contracts\ClaimsResolver::class,
    AppClaimsResolver::class,
);
```

The bundled `StandardClaimsResolver` maps same-named user attributes, when present: under
`profile`, `name`, `locale` (from `$user->locale`), `zoneinfo` (from `$user->timezone`) and
`updated_at`; under `email`, `email` and `email_verified` (from `email_verified_at`); under
`phone`, `phone_number` and `phone_number_verified`; under `address`, the OIDC Core §5.1.1
structured `address` claim from an `address` attribute — an array keeps its standard members
(`formatted`, `street_address`, `locality`, `region`, `postal_code`, `country`), a string becomes
`formatted`. An attribute the user lacks omits its claim. A custom resolver like the one above
replaces it entirely, so re-add the mappings you want to keep.

The `ClaimsResolver` and `ScopeRepository` are the two seams described in full under
[Extension contracts](/advanced/extension-contracts/).
