---
title: Translations
description: "The oidc-ui:: translation namespace, its files and locales, and how to publish and override them."
---

Every string this package renders goes through the `oidc-ui::` translation namespace, loaded
automatically by `UiServiceProvider` — no publishing is required to get a working, localized UI.

## Files and locales

| File | Covers |
| --- | --- |
| `auth.php` | Login, registration, password reset/confirm, email verification, two-factor challenge page copy |
| `common.php` | Shared field labels, placeholders, and actions reused across pages |
| `oauth.php` | The OAuth consent page |
| `security.php` | The settings-page building blocks (two-factor, recovery codes, passkeys, verification) — see [Security components](/ui/security-components/) |

Shipped locales: `en` and `de`.

## Publishing and overriding

```bash
php artisan vendor:publish --tag=oidc-ui-lang
```

This copies the files to `lang/vendor/oidc-ui/{locale}/`. Laravel's package translation loader
merges `lang/vendor/oidc-ui/{locale}/{group}.php` over the package's bundled file on a per-key
basis, so publishing and overriding a single key does not require copying the whole file —
untouched keys keep resolving from the package.

To add a locale the package doesn't ship, publish the `en` files as a starting point and add a
new `lang/vendor/oidc-ui/{locale}/` directory with the same four filenames.
