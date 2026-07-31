---
title: Frontend setup
description: Nothing to register — the passkey components arrive through Lattice's plugin discovery — plus the sprite icon and i18n strings they need.
---

Two of this package's pages — passkey sign-in on the login page and passkey registration on a
settings page — render through frontend components. There is nothing to publish or register for
them: the package's `composer.json` declares `extra.lattice.plugin: "resources/js/plugin.ts"`, a
Lattice component-package plugin exporting `oidc.passkey-verify` and `oidc.passkey-registration`.
The app's own `lattice()` Vite plugin discovers every installed package that declares
`extra.lattice.plugin` and exposes their plugins as the `virtual:lattice/plugins` module, which the
app's registry setup already folds in — installing `bambamboole/laravel-oidc-ui` is enough for
both components to render.

## Sprite icon (`brand_icon`)

`AuthLayout` renders an `Icon` sized from `config('oidc-ui.brand_icon')` (default `logo`) at the
top of every auth-flow page. It resolves against your app's own Lattice SVG sprite, so that
sprite must define a symbol with a matching name — there is no bundled icon. See
[Overriding views](/ui/overriding/) for changing the icon name itself.

## Components for custom factors

A custom factor whose challenge or setup needs browser interaction (a push approval, a
hardware token, ...) plugs in the same way the passkey components do: register a React
component in your app's own Lattice component registry (or in a package via
`extra.lattice.plugin`), wrap it in a PHP `Component` subclass that sets the matching
`type()`, and compose it where it is needed — a rebound `TwoFactorChallengeView` for the
challenge (the prompt's `factor` key tells you when your provider is active), or your own
setup fragment/modal on the settings page. Code-based factors need no frontend work at all:
the challenge page falls back to the OTP code form for unknown provider keys, and the setup
fragment renders a pending enrollment's scalar metadata with the confirm form.

## Passkey UI strings

The passkey components pull their copy from the `oidc-ui` i18n namespace via `useT("oidc-ui")`,
keyed under `passkey.*` (e.g. `passkey.sign-in`, `passkey.register`, `passkey.authenticating` —
see `resources/lang/{locale}/passkey.php`). Every key also has an English string baked into the
component as a `t()` fallback, so nothing is required to get a working UI — override a key by
publishing and editing the `oidc-ui` translations (see [Translations](/ui/translations/)) rather
than the app's own namespace.
