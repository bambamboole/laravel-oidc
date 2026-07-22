---
title: Installation
description: Install laravel-oidc-ui and see what it binds by default.
---

`bambamboole/laravel-oidc-ui` is a [Lattice](https://lattice-php.dev)-powered auth UI for the
OIDC provider: login, registration, password reset, email verification, password confirmation,
two-factor challenge, and OAuth consent, rendered as Lattice pages instead of Blade views.

## Requirements

- PHP `^8.4`
- `bambamboole/laravel-oidc-server` `^0.7` — the OIDC provider this UI renders views for
- `laravel/passport` `^13.4`, `laravel/passkeys` `^0.2`, `lattice-php/lattice` `^0.25`

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

The service provider (`UiServiceProvider`) is auto-discovered, and there is nothing to register
on the frontend either: the package's `composer.json` declares `extra.lattice: { discover: ["src"],
plugin: "resources/js/plugin.ts" }`, so the app's own `lattice()` Vite plugin discovers this
package's pages, components, and translations on its own the moment the dependency is installed
(see [Frontend setup](/ui/frontend-setup/)).

## What gets bound by default

`UiServiceProvider::register()` completes — along with every other provider's `register()` — before
any provider's `boot()` runs, so an app provider that re-binds the same contract (in its own
`register()` or `boot()`) always executes after this default and wins, without forking the
package (see [Overriding views](/ui/overriding/)):

- **All eight auth view contracts** the server package declares — `LoginView`, `RegisterView`,
  `PasswordResetRequestView`, `PasswordResetView`, `EmailVerificationView`,
  `PasswordConfirmationView`, `TwoFactorChallengeView`, and `ConsentView` (the OAuth consent
  page) — each bound to a Lattice page (see [View seams](/auth/overview/)).
- **The `auth` layout** (`AuthLayout`) — registered explicitly via `LayoutRegistry::register()`
  rather than relying on Lattice's filesystem discovery, since that only scans the host app's
  `app/` directory by default and would never see this package's `src/`.
- **The security building blocks** for settings pages — five actions, one form, one fragment,
  one table (see [Security components](/ui/security-components/)).

## Publish (optional)

```bash
php artisan vendor:publish --tag=oidc-ui-config
php artisan vendor:publish --tag=oidc-ui-lang
```

- `oidc-ui-config` writes `config/oidc-ui.php` (`brand_icon`, `logout_route` — see
  [Overriding views](/ui/overriding/)).
- `oidc-ui-lang` writes the `oidc-ui::` translation files (see [Translations](/ui/translations/)).

There is no `oidc-ui-js` (or any other frontend stub) tag — pages and components ship compiled
from this package's own `resources/js/` and arrive through Lattice's plugin discovery, not
publishing.

## Next steps

- [Frontend setup](/ui/frontend-setup/) — the `brand_icon` sprite and the passkey components'
  translation strings.
- [Overriding views](/ui/overriding/) — re-bind a single contract or the auth layout.
- [Security components](/ui/security-components/) — compose 2FA/passkey/verification building
  blocks into your own settings page.
