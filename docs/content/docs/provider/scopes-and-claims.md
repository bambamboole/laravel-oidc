---
title: Scopes & claims
description: The OIDC scope catalog and how an authenticated user is mapped to claims.
---

## Scope catalog

The provider understands the OIDC standard scopes — `openid`, `profile` and `email` — merged with your configured catalog (`scopes.catalog`, below). On a conflict the configured
catalog wins over the built-in OIDC scopes — so you can
override the description of a standard scope simply by defining it in your catalog.

### Scopes belong to a resource

Every scope belongs to exactly one resource server, and a scope only resolves when that resource
is the one being asked for. The realm itself, identified by its issuer URL, is the resource of a
request that carries no RFC 8707 `resource` parameter; the resources registered under
`oidc.resources` own the scopes they list there.

- A catalog scope no resource lists belongs to the realm: requestable without a `resource`, and
  not requestable for some other resource.
- A catalog scope a resource lists belongs to that resource: requestable only when the request
  names it, and never on the realm's default audience.
- The same value under two resources is **two different scopes**. `read` for
  `https://api.internal/orders` and `read` for `https://api.internal/billing` are unrelated
  grants; a request that names neither resource gets neither.
- The OIDC standard scopes belong to the realm itself but resolve under **every** audience, so
  `openid` still produces an id_token for a token requested for an API.
- A scope the requested resources do not own is `invalid_scope` at the authorization endpoint and
  at the `client_credentials` grant, and is dropped at issuance everywhere else — narrowing the
  audience with `resource` at the token endpoint (RFC 8707 §2.2) drops the scopes the narrowed
  resource does not own along with it.

`scopes_supported` in the discovery document is the union over the realm and every registered
resource, which is what a client needs to see before it has chosen one. What each resource itself
advertises stays narrower: its RFC 9728 metadata at
`/.well-known/oauth-protected-resource/<path>` lists only its own scopes.

### Wildcard (`*`)

`*` always resolves as a scope and survives finalization for the `personal_access` and
`client_credentials` grants (e.g. `$user->createToken('cli', ['*'])`), provided the client's
optional scopes contain it. It is stripped for `authorization_code` (interactive) flows, where a
blanket grant has no business being granted on a consent screen. It stands for every scope the
**requested** resources own, not for every scope in the realm: a client holding `*` still cannot
reach an API's scopes without naming that API with `resource`.

### Client scope assignment

Every client carries two lists, `default_scopes` and `optional_scopes`. Default scopes are granted
without being requested; optional scopes only when the request names them. Together they are what
the client may request at all: a known catalog scope outside the assignment is answered with
`invalid_scope` by the authorization endpoint and by the `client_credentials` grant. `*` among the
optional scopes stands for every scope the requested resources own.

An entry may name the resource that owns the scope, the RFC 8707 identifier first and separated by
a space, which limits it to requests for that resource — a space cannot occur in a scope token
(RFC 6749 §3.3), so the two forms never collide:

```php
'optional_scopes' => [
    'openid',                                 // under every resource
    'https://api.internal/orders read',       // only when the orders API is requested
],
```

A bare entry holds under every resource the client may reach, so two APIs that both define `read`
both accept a bare `read` assignment. Qualify the entry when the grant is meant for one of them.
Which resources a client may name at all remains its `allowed_exchange_audiences` allowlist.

New clients receive the realm's `clients.default_scopes` (default `[]`) and
`clients.optional_scopes` (default `['*']`) — from `ClientRepository`, `oidc:client` and
[dynamic registration](/provider/dynamic-client-registration/) alike — so a zero-config
deployment behaves as before: anything may be requested, nothing is granted unasked. `oidc:client`
overrides the realm assignment with `--default-scope` and `--optional-scope`. `openid` is the
typical default scope for an OIDC-only deployment; the client personal access tokens are minted
against needs `*` among its optional scopes for wildcard tokens.

Default scopes are added to an authorization request before consent, so they appear on the consent
screen and in the stored consent like requested scopes; a [hidden](#scope-catalog) scope is the
one exception — granted, never shown. Grants bounded by an earlier artifact (`refresh_token`,
token exchange) never gain scopes their original token did not carry, whatever the client's
defaults are now.

### Registering API scopes

Feed your API scope catalog to the provider through `config/oidc.php`'s `scopes.catalog`
option:

```php
'scopes' => [
    'catalog' => App\Auth\ApiScopes::class,   // or an inline [scope => description] map
],
```

An inline map lists the realm's scopes and the descriptions of the scopes the resources in
`oidc.resources` list; the resource lists decide which of them belongs where.

A class-string must implement `Bambamboole\LaravelOidc\Server\Shared\Scopes\ScopeCatalog`:

```php
interface ScopeCatalog
{
    /**
     * @param  list<string>  $audiences  resolved resource identifiers, never empty
     * @return array<string, string> scope id => description
     */
    public function scopes(array $audiences): array;
}
```

It is asked for the resources of the request and returns only the scopes those resources own —
the realm's issuer URL among the audiences means the realm's own scopes are wanted too. Doing the
split itself is what lets a database-backed catalog answer with one query per audience set and
give the same value a different description under two resources. The scopes each resource lists in
`oidc.resources` are added to whatever it returns.

The scope repository consults it lazily — resolved from the container the first time scopes are
actually enumerated (the consent screen, the discovery document, token issuance), so a
database-backed catalog costs nothing on unrelated requests, keeping key- and db-less artisan
runs working, and the result is memoized per realm and audience set for the life of the
repository; an inline array is read fresh on every enumeration.
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
`updated_at`; under `email`, `email` and `email_verified` (from `email_verified_at`). An attribute the
user lacks omits its claim. The OIDC `phone` and `address` scopes are not built in: add them to your
catalog and resolve their claims in your own resolver when your user model carries the data. A custom resolver like the one above
replaces it entirely, so re-add the mappings you want to keep.

The `ClaimsResolver` and `ScopeRepository` are the two seams described in full under
[Extension contracts](/advanced/extension-contracts/).
