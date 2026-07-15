---
title: Email verification
description: The verification notice, the signed verify route, and the resend endpoint.
---

Email verification uses Laravel's `MustVerifyEmail` machinery. **Your `User` model must implement
`Illuminate\Contracts\Auth\MustVerifyEmail`** for these routes to do anything meaningful — the
verify/resend endpoints abort or no-op otherwise. The notification is triggered by the `Registered`
event fired during [registration](/auth/registration/).

## Routes

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.verification.notice` | `GET` | `auth/email/verify` | `web`, `AuthenticateIdentity:identity` |
| `identity.verification.verify` | `GET` | `auth/email/verify/{id}/{hash}` | `web`, `AuthenticateIdentity:identity`, `signed`, `throttle:6,1` |
| `identity.verification.send` | `POST` | `auth/email/verification-notification` | `web`, `AuthenticateIdentity:identity`, `throttle:6,1` |

All three require an authenticated `identity` session.

## The verification notice

`GET identity.verification.notice` (`EmailVerificationPromptController`) renders your bound
`verifyEmailView`. If the current user already has a verified email, it instead redirects via
`redirect()->intended(...)` to `config('oidc.auth.home')` (default `/dashboard`).

## The signed verify route

`GET identity.verification.verify` is protected by both the `signed` and `throttle:6,1`
(**6 requests per minute**) middleware. It resolves an `EmailVerificationRequest`, calls
`fulfill()` (which marks the email verified and fires `Illuminate\Auth\Events\Verified`), and
redirects to the home URL with a `?verified=1` query flag.

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
