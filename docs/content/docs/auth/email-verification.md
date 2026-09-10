---
title: Email verification
description: The verification notice, the signed verify route, and the resend endpoint.
---

Email verification uses Laravel's `MustVerifyEmail` machinery. **Your `User` model must implement
`Illuminate\Contracts\Auth\MustVerifyEmail`** for these routes to do anything meaningful — the
verify/resend endpoints abort or no-op otherwise. The notification is triggered by the `Registered`
event fired during [registration](/auth/registration/).

Verification is **optional by default**: the routes exist, but nothing forces a user through them.
Set `oidc.auth.email_verification_required` to make an unconfirmed address block the login — see
[Required actions](/auth/required-actions/).

## Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.verification.notice` | `GET` | `auth/email/verify` | `web`, `RequireActionSubject` |
| `identity.verification.verify` | `GET` | `auth/email/verify/{id}/{hash}` | `web`, `RequireActionSubject`, `signed`, `throttle:6,1` |
| `identity.verification.send` | `POST` | `auth/email/verification-notification` | `web`, `RequireActionSubject`, `throttle:6,1` |

All three need a subject, which is **either** an authenticated `identity` session **or** a login
pending on the [`verify_email` required action](/auth/required-actions/) — with
`oidc.auth.email_verification_required` on, no session exists until the address is confirmed, so
these routes cannot sit behind the guard.

## The verification notice

`GET identity.verification.notice` (`EmailVerificationPromptController`) renders through the bound
`EmailVerificationView` contract. If the subject already has a verified email the action is
settled: mid-login that finishes the ceremony, and otherwise it redirects via
`redirect()->intended(...)` to `config('oidc.auth.home')` (default `/dashboard`).

## The signed verify route

`GET identity.verification.verify` is protected by both the `signed` and `throttle:6,1`
(**6 requests per minute**) middleware. It marks the email verified, fires
`Illuminate\Auth\Events\Verified`, and redirects with a `?verified=1` query flag — to the home
URL, or onward through whatever the realm still requires.

The signature only proves the URL was minted here, so the route additionally checks the `id`
against the subject and the `hash` against their address; a link naming a different user is
refused with a **`403`**. It does this itself rather than through Laravel's
`EmailVerificationRequest`, which reads the guard and would find nothing mid-login.

The package wires `VerifyEmail::createUrlUsing(...)` so the verification notification links to this
route as a **temporary signed URL** — `URL::temporarySignedRoute('identity.verification.verify', ...)`
— valid for `config('auth.verification.expire')` minutes (default `60`), carrying the user's `id`
and the `sha1` hash of their email. You do not build this URL yourself.

## The resend endpoint

`POST identity.verification.send` (`SendEmailVerificationNotificationController`), also throttled to
**6 requests per minute**:

- Aborts with **`403`** if the user does not implement `MustVerifyEmail`.
- Redirects to the home URL if the email is already verified.
- Otherwise calls `sendEmailVerificationNotification()` and responds with an empty **`202`** (JSON)
  or a `back()` redirect flashing `status = verification-link-sent` (browser).
