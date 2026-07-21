---
title: Installation
description: Install laravel-oidc-ui and see what it binds by default.
---

`bambamboole/laravel-oidc-ui` is a [Lattice](https://lattice-php.dev)-powered auth UI for the
OIDC provider: login, registration, password reset, email verification, password confirmation,
two-factor challenge, and OAuth consent, rendered as Lattice pages instead of Blade views.

## Requirements

- PHP `^8.4`
- `bambamboole/laravel-oidc-server` `^0.6` — the OIDC provider this UI renders views for
- `laravel/passport` `^13.4`, `laravel/passkeys` `^0.2`, `lattice-php/lattice` `^0.24`

## Install

Two ways to get it:

```bash
# The master package includes server, client, and ui.
composer require bambamboole/laravel-oidc
```

```bash
# Or standalone, alongside the server package.
composer require bambamboole/laravel-oidc-ui
```

The service provider (`UiServiceProvider`) is auto-discovered.

## What gets bound by default

`UiServiceProvider::boot()` runs before any application service provider, so every bind below
is a default an app provider can override by re-binding the same seam later (see
[Overriding views](/ui/overriding/)):

- **All 7 `AuthViewManager` views** — `Login`, `Register`, `RequestPasswordResetLink`,
  `ResetPassword`, `VerifyEmail`, `ConfirmPassword`, `TwoFactorChallenge`.
- **`Passport::authorizationView()`** — the OAuth consent page.
- **The `auth` layout** (`AuthLayout`) — registered explicitly via `LayoutRegistry::register()`
  rather than relying on Lattice's filesystem discovery, since that only scans the host app's
  `app/` directory by default and would never see this package's `src/`.
- **The security building blocks** for settings pages — five actions, one form, one fragment,
  one table (see [Security components](/ui/security-components/)).

## Publish (optional)

```bash
php artisan vendor:publish --tag=oidc-ui-config
php artisan vendor:publish --tag=oidc-ui-lang
php artisan vendor:publish --tag=oidc-ui-js
```

- `oidc-ui-config` writes `config/oidc-ui.php` (`brand_icon`, `logout_route` — see
  [Overriding views](/ui/overriding/)).
- `oidc-ui-lang` writes the `oidc-ui::` translation files (see [Translations](/ui/translations/)).
- `oidc-ui-js` writes the passkey frontend stub components (see
  [Frontend setup](/ui/frontend-setup/)).

## Next steps

- [Frontend setup](/ui/frontend-setup/) — register the passkey components in your Lattice
  registry.
- [Overriding views](/ui/overriding/) — re-bind a single page or the auth layout.
- [Security components](/ui/security-components/) — compose 2FA/passkey/verification building
  blocks into your own settings page.
