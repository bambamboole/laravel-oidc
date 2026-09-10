---
title: Password reset & confirmation
description: The password-reset flow built on Laravel's Password broker, and the password-confirmation screen that gates sensitive actions.
---

This page covers two related flows: **password reset** (for a user who has forgotten their
password) and **password confirmation** (re-proving an already-authenticated user's password before
a sensitive action).

## Password reset

Reset is built on Laravel's `Password` broker (`config('auth.defaults.passwords')`, default
`users`) and your bound [`ResetUserPassword`](/auth/overview/) action. It spans four handlers.

### Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.password.request` | `GET` | `auth/forgot-password` | `web`, `guest:identity` |
| `identity.password.email` | `POST` | `auth/forgot-password` | `web`, `guest:identity`, `throttle:5,1` |
| `identity.password.reset` | `GET` | `auth/reset-password/{token}` | `web`, `guest:identity` |
| `identity.password.update` | `POST` | `auth/reset-password` | `web`, `guest:identity`, `throttle:5,1` |

### Request-link flow

`GET identity.password.request` renders through the bound `PasswordResetRequestView` contract.

`POST identity.password.email` is throttled to **5 requests per minute**, validates `email`
(`required|email`), lowercases it, and calls the broker's `sendResetLink`. The route throttle caps
how often the endpoint can be hit at all; the broker additionally enforces its own per-user window
(returning `RESET_THROTTLED`). On `RESET_LINK_SENT`:

- A JSON request receives `{"status": "..."}` with **`200`**.
- A browser request is redirected `back()` with the translated status in the session.

Any other broker result surfaces as a validation error on the `email` field (JSON) or a
`back()->withErrors(...)` redirect (browser).

The package wires `ResetPassword::createUrlUsing(...)` so the notification's reset link points at the
`identity.password.reset` route (carrying the `token` and `email`) — you do not register that URL
yourself.

### Reset-password flow

`GET identity.password.reset` renders through the bound `PasswordResetView` contract (the
`{token}` is in the URL, and reaches the view as `PasswordResetPrompt::$token`).

`POST identity.password.update` is throttled to **5 requests per minute**, validates `token`, `email`
(`required|email`), and `password` (`required|confirmed`), then calls the broker's `reset`.
`confirmed` means the request must carry a matching `password_confirmation` field — the shipped reset
page renders one, and without the rule a typo would silently commit the first value. Inside the
broker callback, once the token holds, the package:

1. Validates the new password against the realm's [password policy](#password-policy). A violation
   is a `password` validation error and your action does not run.
2. Invokes your `ResetUserPassword` action with the user and full input (your action owns
   persistence and any rules beyond the policy).
3. Rotates the user's remember token and saves.
4. Records the new hash in the password history.
5. Fires `Illuminate\Auth\Events\PasswordReset`.
6. Logs the user in on the `identity` guard.

On `PASSWORD_RESET` the **session is regenerated**, and:

- A JSON request receives `{"status": "..."}` with **`200`**.
- A browser request is redirected to `identity.login` with the status flashed to the session.

Other broker results become an `email` validation error (JSON) or a `back()->withErrors(...)`
redirect (browser).

## Password policy

`CredentialSettings::$password` (a `PasswordPolicy`, read from `oidc.auth.password.*` by the
configured realm) is checked wherever the package accepts a new password: the reset flow above and
[registration](/auth/registration/). It is realm-scoped like every other setting, so a `Realm`
model can return a different policy per tenant.

| Key | Default | Rule |
| --- | --- | --- |
| `min_length` | `8` | Minimum length. |
| `mixed_case` | `false` | Requires upper- and lowercase letters. |
| `numbers` | `false` | Requires a digit. |
| `symbols` | `false` | Requires a symbol. |
| `uncompromised` | `false` | Rejects passwords found in a public breach (haveibeenpwned range lookup, network access at validation time). |
| `history` | `0` | The new password may not repeat any of the last *n* passwords, the current one included. `0` disables the check. |
| `max_age_days` | `null` | After this many days the password counts as expired. `null` never expires. |

The composition rules map onto Laravel's `Password` validation rule, so their messages come from
your `validation.password.*` translations. The history message is the plain string
`The password was used recently. Choose one you have not used before.`, translatable through a JSON
language file.

### History and rotation

`Bambamboole\LaravelOidc\Server\Shared\Credentials\PasswordCredential` (bound to `Credentials\TrackedPasswordCredential`) keeps the hashes a user's password
has had in `oidc_password_history` (a `PasswordHistory` morph on the user). The package writes a row
after every reset and registration it handles, and once on a user's first password login when it has
no row yet, so an existing user's rotation clock starts at their first login after the upgrade. Rows
beyond the history window are pruned on every change. If your application changes a password through
its own code path, call `record($user)` afterwards so history and rotation stay accurate.

`PasswordCredential::changedAt($user)` dates the current password and `isExpired($user)` compares it
with `max_age_days`. A user the package has never tracked is not expired, so turning rotation on does
not lock everybody out at once.

Rotation is **enforced**: an expired password raises the `update_password`
[required action](/auth/required-actions/), which holds the login on the change-password screen and
stops the authorization endpoint from issuing a code until a fresh password is set.

## Changing a password

| Route name | Verb | Path |
| --- | --- | --- |
| `identity.password.change` | `GET` | `auth/user/password` |
| `identity.password.change.store` | `POST` | `auth/user/password` |

The screen renders through the `PasswordUpdateView` seam and persists through the same
[`ResetUserPassword`](/auth/overview/) binding the reset flow uses, so your application
keeps owning the column. The realm's policy and history apply to the new password, and the remember
token is rotated to cut loose the browsers holding the old one.

The **current password** is required from a live session but not mid-login: there the user proved a
credential seconds ago, and a login that arrived by [passkey](/auth/login/#passkey-login) or an
[upstream provider](/auth/social-login/) may have no password to recite.

`verify($user, $password)` is the hash check behind the login and confirmation endpoints; use it
instead of `Hash::check` when your own code needs to prove a password.

## Password confirmation

Password confirmation re-proves the current user's password and records a timestamp on the session,
so sensitive actions can require a recent confirmation. It is the mechanism behind the
`RequirePassword` middleware that gates enabling 2FA and managing passkeys (see
[Multi-factor](/auth/multi-factor/)).

### Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.password.confirm` | `GET` | `auth/user/confirm-password` | `web`, `AuthenticateIdentity:identity` |
| `identity.password.confirm.store` | `POST` | `auth/user/confirm-password` | `web`, `AuthenticateIdentity:identity`, `throttle:5,1` |
| `identity.password.confirmation` | `GET` | `auth/user/confirmed-password-status` | `web`, `AuthenticateIdentity:identity` |

`GET identity.password.confirm` renders through the bound `PasswordConfirmationView` contract.

`POST identity.password.confirm.store` is throttled to **5 requests per minute** — it verifies a
credential, so an unlimited endpoint would be a password oracle for a hijacked session — validates
`password` and verifies it through `PasswordCredential` against the current
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
