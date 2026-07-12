# Self-SSO login with an Auth0-style authentication engine (Fortify-free) — design

**Status:** design (approved shape; Phase 1 build-ready)
**Date:** 2026-07-12
**Related:** `laravel-oidc` (OIDC provider + auth engine), `laravel/passkeys`, Laravel core auth.

> **BOUNDARY CORRECTION (supersedes any "app-side controllers" wording below).**
> All the auth **logic** — the controllers/routes for login, logout, register, password reset,
> email verification, password confirmation, 2FA, plus the pipeline/hook/factor engine — lives in
> the **`laravel-oidc` package**, not the starter kit. The package is a reusable identity provider
> (Fortify-equivalent + OIDC + the Auth0 pipeline). It exposes **seams** the consuming app fills,
> exactly like Fortify did and like the app already does for OIDC consent today:
> - **View bindings** — `Oidc::loginView(...)`, `registerView(...)`, `resetPasswordView(...)`, etc.
>   (the app binds its Lattice pages).
> - **Action bindings** — `Oidc::createUsersUsing(...)`, `resetUserPasswordsUsing(...)` (the app
>   binds domain actions like `CreateNewUser`, which also provisions a personal project).
> The starter kit keeps ONLY: the Lattice auth pages, the domain actions, the `User` model, config
> values, and its own tests. Package auth is tested in isolation via testbench.
> Wherever a section below says "App: … controllers", read it as "**Package** controllers + a seam
> the app fills." §6 is updated accordingly.

## 1. Goal

Make the app log its own users in through its own `laravel-oidc` provider — the real
Authorization-Code + PKCE flow — so every login dogfoods `authorize → code → id_token → JWKS`.
The authentication ceremony is an Auth0-like **pipeline**: primary factor → a **post-login hook**
that can decide whether MFA is required → an MFA challenge across **multiple enrolled factors** →
`acr`/`amr` in the token. Genuine multi-app SSO is on the roadmap, so relying-party semantics are
faithful, not shortcut.

**Fortify is removed.** It is a thin wrapper over Laravel's own auth primitives, and our custom
pipeline + multi-factor model diverges from its fixed login/single-TOTP flow enough that it blocks
more than it helps. We own a small, purpose-built authentication layer on Laravel core
(`Auth`, `Password` broker, `MustVerifyEmail`, `RateLimiter`) + `pragmarx/google2fa` (TOTP) +
`laravel/passkeys` (WebAuthn). The reusable **engine** (pipeline, post-login hook, factor registry,
`acr`/`amr`) lives in the package; the app owns thin credential/account controllers and the Lattice
UI.

## 2. The full vision (target architecture)

```
Relying party (app, later other apps)             Identity provider (laravel-oidc engine + app UI)
  GET /login ──redirect──▶ /oauth/authorize ──▶ AUTHENTICATION PIPELINE  (package engine)
                                                   ① primary factor (password | passkey)
                                                   ② post-login hook  (decision + claims)
                                                        · requireMfa(types?) · deny(reason)
                                                        · addClaims(...)  · (later) redirect(url)
                                                   ③ MFA challenge (if required)
                                                        · user picks among enrolled factors
                                                        · totp | webauthn | recovery | (email|sms)
                                                   ④ identity session + acr/amr/auth_time
                                                   ⑤ consent (auto-skipped for trusted client)
                                                   ⑥ code
  GET /login/callback ◀──code── exchange at /oauth/token (real HTTP) + validate id_token via JWKS
  Auth::guard('web')->login(user)  ─────────────▶ app session
```

Mapping to Auth0: `/authorize` = the authorization request; the **package engine** = the connection
+ Actions runtime; the **post-login hook** = a Post-Login Action; the **factor registry** = MFA
enrollments; id_token `acr`/`amr` = the reported authentication context.

Sub-projects, sequenced. Only 1a→1b are prerequisites for the rest.

| Phase | Scope | Spec |
| --- | --- | --- |
| **1a** | **De-Fortify**: replace Fortify with an owned Identity module, no behavior change | **this doc** |
| **1b** | **Self-SSO**: two-guard split + RP module, app logs in via its own OIDC | **this doc** |
| **2** | Auth pipeline + post-login hook (seam + adaptive example) + `acr`/`amr` | own spec |
| **3** | Multi-method MFA (factor store, registry, selector, unified UI) | own spec |
| **4** | Adaptive & passwordless (`acr_values` step-up, risk logic, passkey-as-primary) | own spec |

## 3. Two sessions, two roles (the foundation)

The circularity — `/authorize` needs an authenticated session, but that session is what login is
trying to create — is resolved by splitting the two "authenticated" states across two guards:

| Role | Guard | Owns | Sees factors |
| --- | --- | --- | --- |
| **Identity provider** (engine + our auth UI) | `identity` (session) | `/auth/*` credential/MFA routes; establishes *who you are* | yes |
| **Relying party** (the app) | `web` (session) | `/login`, `/dashboard`, all `#[AsPage]`s; establishes *this app's session* | no — trusts the id_token |

App pages keep `web`/`auth` — no churn to the many `#[AsPage]` middleware lists. Because we own all
auth routes now, there is **no Fortify route-name collision**: the RP owns `login`/`logout`; the
identity credential/MFA routes live under `/auth/*` with `identity.*` names; each guard's
unauthenticated redirect is set explicitly, so no login loop.

## 4. Phase 1a — De-Fortify (owned Identity module, no behavior change)

**Objective:** remove `laravel/fortify` and reproduce today's behavior exactly with an owned module,
so the change is a parity-checkable refactor before any new flow is added. Single `web` guard still
(the two-guard split is 1b). Preserves password login, registration, password reset, email
verification, password confirmation, **single-TOTP 2FA + recovery codes**, and passkeys.

### 4a.1 What we build (app, mostly wiring over Laravel core)

Thin controllers/actions under `app/Auth/` (the existing namespace; Lattice pages + actions stay):

- **Login / logout** — `Auth::attempt` + session regeneration + the existing `login` rate limiter.
- **Registration** — controller → existing `CreateNewUser` action → `Auth::login` + `Registered`.
- **Password reset** — Laravel `Password` broker (`sendResetLink` / `reset`) → existing
  `ResetUserPassword` action. Uses the framework `password_reset_tokens` table.
- **Email verification** — Laravel `MustVerifyEmail` + `VerifyEmail` signed URLs + `verified`
  middleware (already applied on app pages).
- **Password confirmation** — Laravel core `password.confirm` + `RequirePassword`.
- **TOTP 2FA (single secret, preserved)** — reimplement enable/confirm/challenge/recovery over
  `pragmarx/google2fa` (which Fortify used under the hood) against the existing `two_factor_secret`
  / `two_factor_recovery_codes` columns. The existing `EnableTwoFactorAuthenticationAction`,
  `ConfirmTwoFactorForm`, `TwoFactorSetupFragment`, `ManagesTwoFactor` are rewired to our provider
  instead of Fortify's `TwoFactorAuthenticationProvider`.
- **Passkeys** — `laravel/passkeys` used directly (it does not require Fortify); the existing
  `PasskeyRegistration`/`PasskeyVerify` components + `PasskeysTable` stay.
- **Rate limiters** — the `login` / `two-factor` / `passkeys` limiters move from
  `FortifyServiceProvider` into an `AuthServiceProvider` (app), unchanged.

### 4a.2 What we remove

`laravel/fortify` (composer), `config/fortify.php`, the Fortify view bindings + Fortify 2FA use in
`FortifyServiceProvider` (the provider is repurposed to register our routes/rate-limiters or
replaced). Add direct deps `pragmarx/google2fa` (+ `bacon/bacon-qr-code` for the QR) and, if it was
only transitive via Fortify, `laravel/passkeys`. (Implementer confirms the current dep graph.)

### 4a.3 Tests (parity)

Feature tests asserting today's behavior is unchanged: login (valid/invalid/throttled), logout,
registration, password reset (request + reset), email verification (signed URL), password
confirmation, TOTP enable→confirm→challenge→recovery, passkey register + login. The existing auth
test suite is the parity oracle — it must stay green with only route/action-name adjustments.

## 5. Phase 1b — Self-SSO (two-guard, app as its own RP)

### 5.1 New package capability: a relying-party (RP) module

Self-contained module `Bambamboole\LaravelOidc\RelyingParty`, independent of the provider internals
(speaks to any OIDC provider over discovery/JWKS/HTTP). Config `config('oidc.relying_party')`:

```php
'relying_party' => [
    'enabled' => env('OIDC_RP_ENABLED', false),
    'issuer' => env('OIDC_RP_ISSUER'),             // discovery base; for self-SSO = own issuer
    'client_id' => env('OIDC_RP_CLIENT_ID'),
    'client_secret' => env('OIDC_RP_CLIENT_SECRET'),
    'redirect_uri' => env('OIDC_RP_REDIRECT_URI'), // e.g. https://app.test/login/callback
    'scopes' => ['openid', 'profile', 'email'],
    'login_guard' => env('OIDC_RP_LOGIN_GUARD', 'web'),
    'routes' => [
        'login' => ['path' => 'login', 'name' => 'login'],
        'callback' => ['path' => 'login/callback', 'name' => 'login.callback'],
        'logout' => ['path' => 'logout', 'name' => 'logout'],
    ],
    'redirect_after_login' => env('OIDC_RP_HOME', '/dashboard'),
],
```

- **`OidcDiscovery`** — fetch + cache discovery doc + JWKS (HTTP, per `issuer`).
- **`RelyingParty::redirect()`** — generate `state`, `nonce`, PKCE `code_verifier`/`challenge`
  (S256); stash in session; build the authorize URL; redirect.
- **`RelyingParty::handleCallback(Request)`** — verify `state`; **real HTTP** POST to the token
  endpoint (`Http::asForm()->post`); validate the `id_token` (5.2); resolve the local user from
  `sub`; `Auth::guard(login_guard)->login($user)`; store tokens in session; redirect home.
- **`IdTokenValidator`** — `lcobucci/jwt` + JWKS: RS256 signature by `kid`; `iss`/`aud`/`azp`
  exact; `exp`/`nbf`/`iat` within skew; `nonce` == session nonce; single-use.
- **`resolveUser(sub, claims)`** — config/closure seam; default resolves the `login_guard` provider
  by primary key. (Auto-provisioning is out of scope for now.)
- **Routes** (when `relying_party.enabled`): `login`, `login/callback`, `logout`; `/login` while
  already `web`-authed → straight to `redirect_after_login`.

### 5.2 Provider-side additions (package)

1. **Trusted-client auto-consent** — `config('oidc.trusted_clients')` (client ids); the
   `AuthorizationController` auto-approves (skips consent) for those clients.
2. **Configurable unauthenticated-login redirect (anti-loop)** — `config('oidc.login_route')`
   (route name/path, default `login`); the app sets it to the identity credential route
   (`identity.login`), so `/authorize` sends unauthenticated users to the credential form, never to
   the RP entry.

### 5.3 App wiring

- `config/auth.php`: add an `identity` guard (`session`, `users` provider) — a session series
  distinct from `web`.
- The owned auth module (from 1a) now authenticates the **`identity`** guard; its routes move under
  `/auth` with `identity.*` names; after it authenticates, it returns to `/login` (the RP entry),
  which completes the OIDC round-trip.
- `config('passport.guard') = 'identity'` (the provider binds the authorize `StatefulGuard` to it).
- `config('oidc.login_route') = 'identity.login'`; `config('oidc.trusted_clients') =
  [env('OIDC_FIRST_PARTY_CLIENT')]`.
- RP points at self: `OIDC_RP_ISSUER` = own issuer; `OIDC_RP_CLIENT_ID`/`SECRET` = the existing
  **first-party client** (add `/login/callback` as a redirect URI in the seeder); `login_guard =
  web`.
- **SSO logout:** `route('logout')` clears `web` and redirects to `/oauth/logout` (`end_session`,
  `id_token_hint`) so the `identity` session and other RPs sign out too.

### 5.4 Route / redirect reconciliation (no loops)

- **RP owns `login`/`logout`** (name + path). `route('login')` links and the `web` guard's
  unauthenticated redirect resolve here → the OIDC round-trip.
- **Identity credential/MFA routes** under `/auth/*` with `identity.*` names (`identity.login`).
- **Per-guard redirects:** `web` → `route('login')` (RP); `identity` + `/authorize` →
  `route('identity.login')`. Nothing on the identity side redirects to the RP entry, so no loop.
- Because we removed Fortify, there is exactly one route named `login` — no collision to resolve.

### 5.5 Data flow & errors

Happy path = §2 steps ①→⑥ (password-only in Phase 1). Failures: `state` mismatch / stale PKCE →
restart at `login`; token endpoint error → restart; invalid id_token (sig/iss/aud/exp/nonce) →
generic "sign-in failed", no session; unknown `sub` → deny; already `web`-authed at `/login` →
straight home.

### 5.6 Tests (1b)

Feature: full self-SSO (unauthenticated `/dashboard` → `/login` → `/authorize` → `identity` login →
callback → `web` session + dashboard; id_token fetched + validated over real HTTP; trusted-client
consent skipped). Negatives: tampered `state`, replayed/expired code, id_token wrong
`aud`/`nonce`/`iss`, unknown `sub`. Anti-loop: `/authorize` unauthenticated → `identity.login`;
`/login` while authed → home. Logout clears `web` + `end_session`. Package: discovery/JWKS caching,
`IdTokenValidator` accept/reject matrix, PKCE/state/nonce single-use.

## 6. Component boundaries

- **Package — provider:** authorize ownership, trusted-client auto-consent, `oidc.login_route`.
- **Package — auth (all of it):** controllers + routes for login, logout, register, password reset,
  email verification, password confirmation, 2FA; the engine (pipeline, post-login hook
  `LoginContext`/`LoginResult`, factor-registry contracts + default factors TOTP/WebAuthn/recovery,
  `acr`/`amr`, pipeline session state); the `/auth` route group + `identity` guard defaults. Exposes
  **view bindings** (`Oidc::loginView(...)`, `registerView(...)`, …) and **action bindings**
  (`Oidc::createUsersUsing(...)`, `resetUserPasswordsUsing(...)`) for the app to fill. Reusable
  across IdP apps; tested via testbench.
- **Package — relying party:** discovery, PKCE, callback, strict id_token validation, guard login.
- **App (thin consumer):** the Lattice auth pages (bound as views), domain actions (`CreateNewUser`
  etc., bound as create-user/reset callbacks), the `User` model, config values (issuer, guards,
  first-party client + callback URI seeder, RP self-config, SSO logout target), and its own tests.
- **Laravel core / google2fa / laravel-passkeys:** credential + token + TOTP + WebAuthn primitives
  the package builds on.

## 7. Later phases (architecture-level)

### Phase 2 — authentication pipeline + post-login hook + acr/amr (package engine)

The identity establishment becomes a pipeline invoked by the owned login flow (it reads the pending
authorize context — client, `acr_values` — from the session):

```php
Oidc::postLogin(function (LoginContext $ctx, LoginResult $result) {
    // $ctx: user, client, requested acr_values, scopes, ip, userAgent, amr-so-far, isNewDevice
    if ($ctx->requestsAcr('mfa') || $ctx->isNewDevice()) {
        $result->requireMfa();          // Auth0 api.multifactor.enable() analog
    }
    // $result->deny('blocked');         // api.access.deny() analog
    // $result->addClaim('groups', ...); // unified with the existing claim writer
});
```

`LoginContext`/`LoginResult` run once after the primary factor, before the code. **Ships with a real
adaptive example**: require MFA on a new device/IP (recognized-device signal). `acr`/`amr` captured
from what happened (`pwd`, `otp`/`mfa`, `webauthn`) and emitted into id_token + userinfo; `acr` = a
configurable level. This is the previously-deferred `acr`/`amr` item.

### Phase 3 — multi-method MFA (package registry + app UI)

`user_mfa_factors` store (many per user), a factor registry of pluggable types (`totp` over
google2fa, `webauthn`/passkey as a second factor via `laravel/passkeys`, `recovery`; later
`email`/`sms`), a challenge selector, and a unified "Security methods" management area. The single
`two_factor_secret` from Phase 1a migrates into a `totp` factor row.

### Phase 4 — adaptive & passwordless

`acr_values` step-up in `/authorize`; richer risk logic in the post-login hook; passkey as a
primary/passwordless factor (`amr=[webauthn]`).

## 8. Open decisions (resolve during planning)

- Whether Phase 1a repurposes `FortifyServiceProvider` into an `AuthServiceProvider` or replaces it.
- Exact current dependency graph for `laravel/passkeys` / `pragmarx/google2fa` (direct vs
  transitive via Fortify) before removing Fortify.
- `end_session` logout: immediate vs. a "sign out everywhere?" choice.
- Device-recognition storage for the Phase 2 adaptive example (signed cookie vs. table).
