---
title: Social login
description: Signing in with an upstream identity provider — configuration, JIT provisioning, resolution order, linking, and custom drivers.
---

Social login is an additional authentication method: a user signs in through an upstream identity
provider (IdP) instead of (or alongside) a password. Four drivers ship out of the box — `google`,
`apple`, `github`, and a generic `oidc` driver for any OIDC-compliant IdP — resolved by
`SocialProviderRegistry` from `oidc.social.providers`. Custom drivers can be registered with
`Oidc::extendSocialProvider(...)`.

## Routes and the accounts table

| Route name | Verb | Path |
| --- | --- | --- |
| `identity.social.redirect` | `GET` | `auth/social/{provider}` |
| `identity.social.callback` | `GET`, `POST` | `auth/social/{provider}/callback` |

`identity.social.redirect` builds the upstream authorize URL and stores state/PKCE/nonce in the
session (`SocialAuthenticationController::redirect`). `identity.social.callback` validates the
callback against that pending authorization and exchanges the code for the upstream identity
(`SocialAuthenticationController::callback`).

Every upstream identity linked to a local user is stored in `oidc_social_accounts`
(`2026_07_16_000008_create_oidc_social_accounts_table`): a polymorphic
`authenticatable_type`/`authenticatable_id` pair, `provider`, `provider_user_id` (unique together),
`email`, `name`, `nickname`, `avatar`, `access_token`, `refresh_token`, `token_expires_at`, and the
upstream claims as `raw` JSON.

Publish it with the package's other migrations:

```bash
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate
```

## Configuration

```php
'social' => [
    'link_by_verified_email' => true,
    'auto_provision' => true,
    'providers' => [
        'google' => [
            'driver' => 'google',
            'client_id' => env('OIDC_SOCIAL_GOOGLE_CLIENT_ID'),
            'client_secret' => env('OIDC_SOCIAL_GOOGLE_CLIENT_SECRET'),
        ],
        'apple' => [
            'driver' => 'apple',
            'client_id' => env('OIDC_SOCIAL_APPLE_CLIENT_ID'),
            'team_id' => env('OIDC_SOCIAL_APPLE_TEAM_ID'),
            'key_id' => env('OIDC_SOCIAL_APPLE_KEY_ID'),
            'private_key' => env('OIDC_SOCIAL_APPLE_PRIVATE_KEY'),
        ],
        'github' => [
            'driver' => 'github',
            'client_id' => env('OIDC_SOCIAL_GITHUB_CLIENT_ID'),
            'client_secret' => env('OIDC_SOCIAL_GITHUB_CLIENT_SECRET'),
        ],
    ],
],
```

- `link_by_verified_email` — attach the upstream identity to an existing local user when the
  provider reports a verified email that matches.
- `auto_provision` — create a local user on first social login via the action registered with
  `Oidc::createUsersFromSocialUsing()` (see [JIT provisioning](#jit-provisioning)).
- A provider entry is only enabled once its `client_id` is set; an empty or missing `client_id`
  disables it without removing the entry (`SocialProviderRegistry::get`).

Apple's `private_key` is a PEM string; escape its newlines as `\n` in `.env` — the same convention
as `OIDC_PRIVATE_KEY`/`OIDC_PUBLIC_KEY` — and the provider unescapes them at runtime.

Any other OIDC-compliant IdP can be added with the generic `oidc` driver and an `issuer`:

```php
'keycloak' => [
    'driver' => 'oidc',
    'issuer' => env('OIDC_SOCIAL_KEYCLOAK_ISSUER'),
    'client_id' => env('OIDC_SOCIAL_KEYCLOAK_CLIENT_ID'),
    'client_secret' => env('OIDC_SOCIAL_KEYCLOAK_CLIENT_SECRET'),
],
```

The `oidc` driver discovers endpoints from `{issuer}/.well-known/openid-configuration` and
verifies the returned `id_token` against the upstream JWKS.

## JIT provisioning

Without a registered action, `auto_provision` has no effect: provisioning is effectively disabled,
and an upstream identity that resolves to no existing user fails to sign in. Register the action in
a service provider's `boot()`:

```php
use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Support\Str;

Oidc::createUsersFromSocialUsing(function (Bambamboole\LaravelOidc\Auth\Social\SocialUser $socialUser, string $provider) {
    return User::create([
        'name' => $socialUser->name,
        'email' => $socialUser->email,
        'password' => Str::random(40),
    ]);
});
```

The action receives the normalized `SocialUser` and the provider key, and must return the created
`Authenticatable`. There is no password the user knows — `Str::random(40)` is a placeholder value,
not a credential to communicate.

The package's verified-email gate (see [Resolution order](#resolution-order)) only protects its own
linking step in `SocialAccountManager`. If the provision action itself does
`User::firstOrCreate(['email' => $socialUser->email])`, it re-attaches an unverified upstream email
to an existing local account, reopening the account-takeover path the gate exists to close. Provision
actions should always create a fresh user and let the package's verified-email linking handle matches
on subsequent logins.

## Login buttons

`Oidc::socialProviders()` returns the configured and credentialed providers, keyed by provider key,
for rendering login buttons:

```blade
@foreach (Oidc::socialProviders() as $key => $provider)
    <a href="{{ route('identity.social.redirect', ['provider' => $key]) }}">
        Sign in with {{ ucfirst($key) }}
    </a>
@endforeach
```

## Resolution order

On callback, `SocialAccountManager::resolveUser` resolves the local user in this order:

1. **Existing link** — an `oidc_social_accounts` row already matches `provider` +
   `provider_user_id`. The account's stored fields are refreshed from the latest `SocialUser`.
2. **Verified-email link** — when `link_by_verified_email` is enabled and the upstream identity
   reports a verified email, an existing user with that email is matched and linked.
3. **JIT provisioning** — when `auto_provision` is enabled and a `createUsersFromSocialUsing` action
   is registered, a new user is created and linked.
4. **Error** — none of the above resolved a user; the callback redirects back to
   `identity.login` with a `social` error.

A successful resolution still goes through the same post-login pipeline as password login. If the
resolved user has a challengeable MFA enrollment, they are redirected to the two-factor challenge
before the session is established — social login does not bypass MFA.

## Linking and unlinking

| Route name | Verb | Path |
| --- | --- | --- |
| `identity.social.link` | `GET` | `auth/user/social/{provider}` |
| `identity.social.destroy` | `DELETE` | `auth/user/social/{socialAccount}` |

Both require an authenticated `identity` session **and** a recent password confirmation
(`RequirePassword::using('identity.password.confirm')` — see
[Password confirmation](/auth/passwords/)).

`identity.social.link` starts the same upstream redirect as login, but with intent `link` instead
of `login`. On callback, the identity is attached to the *current* user rather than used to resolve
one. If that upstream identity is already linked to a **different** user, linking fails and redirects
back with a `social` error ("This account is already linked to another user."); otherwise the
account is linked (or its stored fields refreshed, if already linked to the current user).

`identity.social.destroy` deletes the `SocialAccount` (403 if it does not belong to the current
user) and returns an empty **`200`** response (JSON) or a `back()` redirect flashing the status
(browser).

## Custom drivers

Register a custom driver factory in a service provider's `boot()`:

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

Oidc::extendSocialProvider('my-driver', function (string $key, array $config) {
    return new App\Auth\Social\MyDriverProvider($key, $config);
});
```

The closure receives the provider's key (its entry name under `oidc.social.providers`) and its
config array, and must return a `Bambamboole\LaravelOidc\Auth\Social\Contracts\SocialProvider`
implementation (`key()`, `redirect(Request $request, string $intent)`, and
`user(Request $request, PendingAuthorization $pending): SocialUser`). Reference the entry with the
matching `driver`:

```php
'my-provider' => [
    'driver' => 'my-driver',
    'client_id' => env('OIDC_SOCIAL_MY_PROVIDER_CLIENT_ID'),
    'client_secret' => env('OIDC_SOCIAL_MY_PROVIDER_CLIENT_SECRET'),
],
```

## Apple specifics

Sign in with Apple deviates from stock OIDC in ways the `AppleProvider` accounts for:

- The callback arrives as a cross-site `POST` (`response_mode=form_post`), which cannot carry a
  CSRF token because the browser does not send it as a same-site request. The
  `identity.social.callback` route is therefore CSRF-exempt by design; the OAuth `state` parameter
  is the defense against forged callbacks instead.
- The controller bounces a `POST` callback to a top-level `GET` (303 redirect with the same query
  parameters) before validating `state`, since the session cookie is not sent on the original
  cross-site `POST` under `SameSite=Lax`.
- Apple sends the user's name only **once**, on first consent, as a JSON `user` request parameter —
  it never appears in the `id_token` on subsequent logins. `SocialAccountManager` never nulls out a
  previously stored name when a later sync omits it.
