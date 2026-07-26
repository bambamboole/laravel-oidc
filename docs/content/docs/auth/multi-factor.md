---
title: Multi-factor authentication
description: The pluggable factor system, the challenge flow, the management endpoints, and how enrollment, challenge, and amr fit together.
---

The package ships a pluggable multi-factor system. A **factor provider** knows how to enroll,
challenge, and verify one kind of second factor; a **registry** holds them; the challenge flow and
management endpoints drive them. Three providers ship out of the box.

## The factor system

### `FactorProvider`

`Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\FactorProvider` is the contract every factor
implements:

| Method | Purpose |
| --- | --- |
| `key(): string` | The provider's stable key (e.g. `totp`, `recovery_code`, `webauthn`) |
| `isBackup(): bool` | Whether this factor is a fallback (backup factors are excluded from the primary challenge list) |
| `enrollments($user): list<FactorEnrollment>` | The user's enrollments for this factor |
| `beginChallenge($user, $enrollment): FactorChallenge` | Produce a challenge (public data for the browser + private state) |
| `verify($user, $challenge, $response): FactorVerification` | Verify a response; returns `verified`, the satisfied `amr`, and metadata |

`EnrollableFactorProvider` extends it with `beginEnrollment(...)` and `revoke(...)` for factors the
user can add and remove themselves.

### `FactorRegistry`

`FactorRegistry` registers providers by key (duplicate keys throw a `LogicException`) and resolves
them. Two lookups matter for login:

- `enrollments($user)` — every enrollment across all providers.
- `challengeableEnrollments($user, $providerKeys)` — enrollments that are **confirmed**
  (`confirmedAt !== null`), from providers that are **not backup**, optionally filtered to a set of
  provider keys.

Providers are registered from `config('oidc.auth.factors')`, which defaults to all three shipped
providers:

```php
'factors' => [
    TotpFactorProvider::class,
    RecoveryCodeProvider::class,
    WebAuthnFactorProvider::class,
],
```

## The shipped providers

| Provider | Key | Backup? | Backed by | `amr` on success |
| --- | --- | --- | --- | --- |
| `TotpFactorProvider` | `totp` | no | `pragmarx/google2fa` | `otp` |
| `RecoveryCodeProvider` | `recovery_code` | yes | one-time recovery codes | `otp` (with `backup` metadata) |
| `WebAuthnFactorProvider` | `webauthn` | no | `laravel/passkeys` (WebAuthn) | `webauthn` |

**TOTP** enrolls an authenticator-app secret (length `oidc.auth.two_factor.secret_length`, default
`16`), verifies codes within a `window` (default `1`) using replay-resistant
`verifyKeyNewer` bookkeeping, and can render the enrollment as a QR-code SVG/URL.

**Recovery codes** are a backup factor: they only present an enrollment once TOTP is confirmed and
codes exist. Verification consumes a single code (locked + transactional) and marks it used.

**WebAuthn / passkeys** reuses `laravel/passkeys` to generate assertion options and verify the
returned credential; its verification reports `phishing_resistant` and `user_verified` metadata.

## The challenge flow

When [login](/auth/login/) finds a challengeable enrollment, it stashes the pending user and
redirects to the challenge:

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.two-factor.login` | `GET` | `auth/two-factor-challenge` | `web`, `guest:identity` |
| `identity.two-factor.login.options` | `GET` | `auth/two-factor-challenge/options` | `web`, `guest:identity`, `throttle:5,1` |
| `identity.two-factor.login.store` | `POST` | `auth/two-factor-challenge` | `web`, `guest:identity`, `throttle:5,1` |

`GET identity.two-factor.login.options` issues the pending factor's challenge: the private
half is persisted in the session for the verification step, the public half (for WebAuthn,
the browser request options) is returned as JSON. Issuance and verification are separate
requests by design — each verification attempt consumes the stored state and needs a fresh
challenge.

`GET identity.two-factor.login` renders through the bound `TwoFactorChallengeView` contract, or
redirects to `identity.login` if there is no pending challenge on the session.

`POST identity.two-factor.login.store` (throttled **5/minute**) validates `code` and `recovery_code`
(both `nullable|string`), resolves the pending user, and picks the provider: `recovery_code` when a
recovery code is submitted, otherwise the stashed `login.factor` (default `totp`). It runs
`beginChallenge` + `verify`; a failed verification throws a validation error. On success it:

1. Adds the verified factor's `amr` to the session's authentication methods.
2. Logs the user in on the `identity` guard (honoring the stashed `remember` flag).
3. **Regenerates the session.**
4. Responds with an empty **`204`** (JSON) or `redirect()->intended(...)` to the home URL (browser).

## Management endpoints

All management endpoints require an authenticated `identity` session **and** a recent password
confirmation (`RequirePassword::using('identity.password.confirm')` — see
[Password confirmation](/auth/passwords/)).

Enrollment, confirmation, and revocation run exclusively through the
[provider-keyed endpoints](#provider-keyed-enrollment) below. The former Fortify-compatible
routes `identity.two-factor.enable`, `identity.two-factor.confirm`, and
`identity.two-factor.disable` have been removed — use `identity.two-factor.enroll`,
`identity.two-factor.enroll.confirm`, and `identity.two-factor.revoke` (all with
`provider=totp`) instead.

The TOTP-specific and recovery-code endpoints without a generic equivalent remain:

| Route name | Verb | Path | Purpose |
| --- | --- | --- | --- |
| `identity.two-factor.qr-code` | `GET` | `auth/user/two-factor-qr-code` | `{ "svg": ..., "url": ... }` for the latest TOTP factor |
| `identity.two-factor.secret-key` | `GET` | `auth/user/two-factor-secret-key` | `{ "secretKey": ... }` (404 if not enabled) |
| `identity.two-factor.recovery-codes` | `GET` | `auth/user/two-factor-recovery-codes` | The unused recovery codes |
| `identity.two-factor.regenerate-recovery-codes` | `POST` | `auth/user/two-factor-recovery-codes` | Replace the recovery codes |

Regenerate returns an empty **`200`** response (JSON) or a `back()` redirect flashing a
status key to the session (browser). The read endpoints (`qr-code`, `secret-key`,
`recovery-codes`) return **`404`** while no TOTP factor exists.

## Provider-keyed enrollment

Any provider implementing `EnrollableFactorProvider` (`beginEnrollment`,
`confirmEnrollment`, `revoke`) is enrollable through generic endpoints — a new factor type
needs no package changes. All of them share the management middleware above.

| Route name | Verb | Path | Purpose |
| --- | --- | --- | --- |
| `identity.two-factor.factors` | `GET` | `auth/user/two-factor/factors` | Every enrollment across all providers |
| `identity.two-factor.enroll` | `POST` | `auth/user/two-factor/{provider}` | Begin an enrollment; the response `metadata` carries the setup payload (e.g. the TOTP secret, exposed only here) |
| `identity.two-factor.enroll.confirm` | `POST` | `auth/user/two-factor/{provider}/confirm` | Confirm with `enrollment_id` plus the provider's proof (e.g. `code`) |
| `identity.two-factor.revoke` | `DELETE` | `auth/user/two-factor/{provider}/{enrollment}` | Remove one enrollment |

Repeating `enroll` for `totp` while an unconfirmed enrollment exists returns that pending
enrollment (same id, same secret) instead of creating another; enrolling alongside a
confirmed factor starts a fresh pending enrollment (re-enrollment).

Recovery codes are generated automatically when a first factor is confirmed and removed
when the last challengeable factor is revoked. Unknown provider keys — and providers with
their own enrollment ceremony, like `webauthn` (see
[Passkey management](#passkey-management)) — return `404` from the enroll endpoints while
still appearing in the factors listing.

### Passkey management

Passkeys are registered and removed through `laravel/passkeys`. The registration and delete
endpoints are gated the same way (`identity` session + `RequirePassword`); the confirm
endpoints require only an authenticated session — they *are* a password-confirmation
mechanism, so they cannot demand a prior confirmation themselves. The options/store/confirm
endpoints also carry `throttle:5,1`:

| Route name | Verb | Path | `RequirePassword` |
| --- | --- | --- | --- |
| `identity.passkey.registration-options` | `GET` | `auth/user/passkeys/options` | yes |
| `identity.passkey.store` | `POST` | `auth/user/passkeys` | yes |
| `identity.passkey.destroy` | `DELETE` | `auth/user/passkeys/{passkey}` | yes |
| `identity.passkey.confirm-options` | `GET` | `auth/passkeys/confirm/options` | no |
| `identity.passkey.confirm` | `POST` | `auth/passkeys/confirm` | no |

(Passkey *login* — the passwordless sign-in path — lives on the [login page](/auth/login/).)

## Configuration

```php
'two_factor' => [
    'challenge_providers' => ['totp'], // which providers are offered at the login challenge
    'secret_length' => 16,             // TOTP secret length
    'window' => 1,                     // accepted TOTP time-step window
    'recovery_codes' => 8,             // how many recovery codes are generated
],
```

## Enrollment, challenge, and `amr`

- **Enrollment** happens through the management endpoints (or your own UI on top of a
  `FactorProvider`). A TOTP factor becomes *challengeable* only once it is **confirmed**; enabling
  TOTP also generates the backup recovery codes.
- **Challenge** at login only offers *confirmed, non-backup* factors matching
  `challenge_providers`. Recovery codes are always available as a fallback when the user submits one.
- **`amr`** accrues across factors: the primary password contributes `pwd`, and each verified factor
  adds its own method (`otp`, `webauthn`). The full set is carried on the session and emitted onto
  the issued `id_token`, where the OP derives `acr` from it (`1` for a single method, `2` when more
  than one method was satisfied). How this reaches the token is described on
  [The post-login pipeline](/auth/post-login-pipeline/).
