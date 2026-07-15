---
title: Password reset & confirmation
description: The password-reset flow built on Laravel's Password broker, and the password-confirmation screen that gates sensitive actions.
---

This page covers two related flows: **password reset** (for a user who has forgotten their
password) and **password confirmation** (re-proving an already-authenticated user's password before
a sensitive action).

## Password reset

Reset is built on Laravel's `Password` broker (`config('auth.defaults.passwords')`, default
`users`) and your [`resetUserPasswordsUsing`](/auth/overview/) action. It spans four handlers.

### Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.password.request` | `GET` | `auth/forgot-password` | `web`, `guest:identity` |
| `identity.password.email` | `POST` | `auth/forgot-password` | `web`, `guest:identity` |
| `identity.password.reset` | `GET` | `auth/reset-password/{token}` | `web`, `guest:identity` |
| `identity.password.update` | `POST` | `auth/reset-password` | `web`, `guest:identity` |

### Request-link flow

`GET identity.password.request` renders your bound `requestPasswordResetLinkView`.

`POST identity.password.email` validates `email` (`required|email`), lowercases it, and calls the
broker's `sendResetLink`. The broker itself enforces the per-user throttle window (returning
`RESET_THROTTLED`). On `RESET_LINK_SENT`:

- A JSON request receives `{"status": "..."}` with **`200`**.
- A browser request is redirected `back()` with the translated status in the session.

Any other broker result surfaces as a validation error on the `email` field (JSON) or a
`back()->withErrors(...)` redirect (browser).

The package wires `ResetPassword::createUrlUsing(...)` so the notification's reset link points at the
`identity.password.reset` route (carrying the `token` and `email`) — you do not register that URL
yourself.

### Reset-password flow

`GET identity.password.reset` renders your bound `resetPasswordView` (the `{token}` is in the URL).

`POST identity.password.update` validates `token`, `email` (`required|email`), and `password`
(`required`), then calls the broker's `reset`. Inside the broker callback the package:

1. Invokes your `resetUserPasswordsUsing` action with the user and full input (your action owns the
   password rules and persistence).
2. Rotates the user's remember token and saves.
3. Fires `Illuminate\Auth\Events\PasswordReset`.
4. Logs the user in on the `identity` guard.

On `PASSWORD_RESET` the **session is regenerated**, and:

- A JSON request receives `{"status": "..."}` with **`200`**.
- A browser request is redirected to `identity.login` with the status flashed to the session.

Other broker results become an `email` validation error (JSON) or a `back()->withErrors(...)`
redirect (browser).

## Password confirmation

Password confirmation re-proves the current user's password and records a timestamp on the session,
so sensitive actions can require a recent confirmation. It is the mechanism behind the
`RequirePassword` middleware that gates enabling 2FA and managing passkeys (see
[Multi-factor](/auth/multi-factor/)).

### Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.password.confirm` | `GET` | `auth/user/confirm-password` | `web`, `AuthenticateIdentity:identity` |
| `identity.password.confirm.store` | `POST` | `auth/user/confirm-password` | `web`, `AuthenticateIdentity:identity` |
| `identity.password.confirmation` | `GET` | `auth/user/confirmed-password-status` | `web`, `AuthenticateIdentity:identity` |

`GET identity.password.confirm` renders your bound `confirmPasswordView`.

`POST identity.password.confirm.store` validates `password` and `Hash::check`s it against the current
user's stored password. On success it writes `auth.password_confirmed_at` (the current timestamp) to
the session, then returns an empty **`201`** (JSON) or a `redirect()->intended(...)` to the home URL
(browser). A wrong password throws a validation error with the `auth.password` message.

`GET identity.password.confirmation` returns `{"confirmed": <bool>}`, where the value is `true` while
the last confirmation is newer than `config('auth.password_timeout')` (default `900` seconds).

### Gating sensitive actions

Handlers that mutate a user's security posture — enabling/confirming/disabling 2FA, viewing the QR
code or secret key, regenerating recovery codes, and registering or deleting passkeys — are wrapped
in `RequirePassword::using('identity.password.confirm')`. When the confirmation is stale, that
middleware redirects the user to `identity.password.confirm` before the action runs.
