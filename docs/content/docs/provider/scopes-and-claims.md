---
title: Scopes & claims
description: The OIDC scope catalogue and how an authenticated user is mapped to claims.
---

## Scope catalogue

The provider understands the OIDC standard scopes — `openid`, `profile`, `email`, `address`,
`phone` — merged **over** `Passport::$scopes`. Because the merge favours your app's definitions,
scopes you already define win: you can override the description of a standard scope simply by
defining it yourself.

### Wildcard (`*`) parity

The package mirrors Passport's wildcard behaviour exactly. Passport treats `*` as always valid and
grants it for the `password`, `personal_access`, and `client_credentials` grants (e.g.
`$user->createToken('cli', ['*'])`). Here `*` resolves as a scope and survives finalization for
those grant types, and is stripped for `authorization_code` (interactive) flows.

The scope catalogue is provided by the `ScopeRepository` contract — see
[Extension contracts](/advanced/extension-contracts/) to swap it.

## Claims

`Bambamboole\LaravelOidc\Contracts\ClaimsResolver` maps an authenticated user to a `ClaimSet`. A
`ClaimSet` is constructed from a `scope => [claim => value]` map. Both the `id_token` builder and
the userinfo endpoint call `forScopes()` on it with the token's granted scopes, so a claim is only
emitted when its scope was granted — and null values are dropped.

```php
use Bambamboole\LaravelOidc\Claims\ClaimSet;
use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class AppClaimsResolver implements ClaimsResolver
{
    public function resolve(Authenticatable $user): ClaimSet
    {
        return new ClaimSet([
            'profile' => ['name' => $user->name],
            'email' => [
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }
}
```

Bind your resolver so the provider uses it:

```php
$this->app->singleton(
    \Bambamboole\LaravelOidc\Contracts\ClaimsResolver::class,
    AppClaimsResolver::class,
);
```

The `ClaimsResolver` and `ScopeRepository` are the two seams described in full under
[Extension contracts](/advanced/extension-contracts/).
