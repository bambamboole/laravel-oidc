# Handoff: build the identity-provider layer into laravel-oidc

## Where this package is going

`laravel-oidc` today is an **OIDC provider** on top of Passport (authorize/token/id_token, JWKS,
discovery, userinfo, introspection, revocation, RFC 8693 token exchange, env-based key rotation).
That work is released (package `main`, v0.1.x line).

The next chapter turns it into a **full, reusable identity provider**: it will own the
authentication logic itself — **Fortify-equivalent + OIDC + an Auth0-style login pipeline** — so a
consuming app drops the package in, binds its own views + a create-user action, and gets login,
registration, password reset, email verification, password confirmation, 2FA (multi-method), and
self-SSO out of the box.

## The load-bearing decision (why you're working in the package, not the app)

All auth **logic** lives in **this package**, not in the consuming app. The package exposes seams
the app fills — exactly like Fortify did, and like this package already does for OIDC consent
(the app provides an `OAuthConsentPage` + a `UserClaimsResolver`, the package owns the flow):

- **View bindings** — `Oidc::loginView(...)`, `registerView(...)`, `resetPasswordView(...)`,
  `verifyEmailView(...)`, `confirmPasswordView(...)`, `twoFactorChallengeView(...)`. The app binds
  its (Lattice) pages.
- **Action bindings** — `Oidc::createUsersUsing(...)`, `resetUserPasswordsUsing(...)`. The app binds
  domain actions (e.g. its `CreateNewUser`, which also provisions a personal project).

The consuming app (the `saas-starter-kit` monorepo) keeps ONLY: its Lattice auth pages, its domain
actions, its `User` model, config values, and its own tests. Everything else is the package.

**Test the package in isolation via testbench** (this repo already has a `workbench/` + testbench
harness). You do not need the app to build/verify the package auth.

## Read these (in `docs/`)

1. **`self-sso-and-auth-engine-design.md`** — the approved design. Note the **BOUNDARY CORRECTION**
   callout at the top and §6: they are authoritative. Some older section bodies still say
   "App: … controllers" — read those as "**Package** controllers + a seam the app fills."
2. **`appside-defortify-parity-reference.md`** — an earlier, *app-side* implementation plan that was
   started in the monorepo before we corrected the boundary. **Do not follow its file locations**
   (it puts controllers in `app/`). It is valuable ONLY as a **parity reference**: it contains the
   exact behavior each owned controller must reproduce (events fired, session regeneration, redirect
   / `back()` / JSON response shapes, route names, and the exact assertions of the app's existing
   auth tests). Reuse that behavior; relocate everything into the package with the view/action seams.

## The roadmap (phased; each phase its own plan)

- **1a — Auth engine in the package (de-Fortify):** package-owned controllers + routes for register,
  password reset, email verification, password confirmation, login, logout, and TOTP 2FA, over
  Laravel core (`Auth`, `Password` broker, `MustVerifyEmail`, `RateLimiter`) + `pragmarx/google2fa`
  + `laravel/passkeys`, with the view/action seams. Behavior mirrors Fortify (parity). This is the
  big one and should be its own set of plans (suggest splitting: account flows first, then
  login+2FA).
- **1b — Self-SSO relying-party module:** a package RP (discovery, PKCE, real-HTTP callback, strict
  id_token validation via JWKS, login into a configurable guard) + trusted-client auto-consent +
  `oidc.login_route`. Lets an app (incl. this one) log in via its own OIDC.
- **2 — Pipeline + post-login hook + `acr`/`amr`:** the authentication becomes a pipeline with a
  post-login decision hook (`requireMfa`/`deny`/`addClaims`), shipping a real adaptive example
  (require MFA on a new device/IP); emit `acr`/`amr`.
- **3 — Multi-method MFA:** `user_mfa_factors` store, factor registry (TOTP/WebAuthn/recovery),
  challenge selector, unified management UI seam.
- **4 — Adaptive & passwordless:** `acr_values` step-up, risk logic, passkey-as-primary.

## How to start

Use the brainstorming/writing-plans/subagent-driven-development flow. The design is already
approved, so you can go straight to **writing-plans for Phase 1a** (package-side), using the parity
reference for exact behavior. Keep the package's gates green throughout: `vendor/bin/pest`,
`vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (zero errors, no new suppressions), and
match the package's conventions (`<?php` then `declare(strict_types=1);`, namespace
`Bambamboole\LaravelOidc`, no narration comments).

## State of the parallel app work

The monorepo branch `feat/defortify-account-flows` holds the discarded app-side exploration
(registration + password reset controllers in `app/`), pushed for reference only. It will be
replaced by the app *binding* the package's auth once this package work lands. Nothing there needs
to be carried forward except the parity behavior captured in the reference doc.
