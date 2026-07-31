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

All three shipped providers are enrollable through the [generic endpoints](#provider-keyed-enrollment):

| Provider | Key | Backup? | Backed by | `amr` on success |
| --- | --- | --- | --- | --- |
| `TotpFactorProvider` | `totp` | no | `pragmarx/google2fa` | `otp` |
| `RecoveryCodeProvider` | `recovery_code` | yes | one-time recovery codes | `otp` (with `backup` metadata) |
| `WebAuthnFactorProvider` | `webauthn` | no | `laravel/passkeys` (WebAuthn) | `webauthn` |

**TOTP** enrolls an authenticator-app secret (length `oidc.auth.two_factor.secret_length`, default
`16`) and verifies codes within a `window` (default `1`) using replay-resistant
`verifyKeyNewer` bookkeeping. The begin-enrollment metadata carries the setup payload —
`secret`, `qr_svg` (rendered QR code), and `qr_url` (the `otpauth://` URL) — exposed only there,
never in the factors listing.

**Recovery codes** are a backup factor with one account-wide enrollment whenever codes exist.
Beginning an enrollment (re)generates the code set and returns the plaintext codes in the
metadata — the only place they appear. Confirmation is a no-op (codes need no proof), and
revocation deletes them. Verification consumes a single code (locked + transactional) and marks
it used.

**WebAuthn / passkeys** reuses `laravel/passkeys`. The challenge issues assertion options for
*all* the user's passkeys and accepts any of them; verification reports `phishing_resistant`
and `user_verified` metadata. Enrollment is a two-step ceremony: begin returns the WebAuthn
creation options in the metadata (and parks them in the session as a synthetic `pending`
enrollment); confirm takes the attestation `credential` and stores the passkey. The
[dedicated passkey routes](#passkey-management) remain for browser-driven registration.

## The challenge flow

When [login](/auth/login/) finds a challengeable enrollment, it stashes the pending user and
redirects to the challenge:

| Route name | Verb | Path | Middleware |
| --- | --- | --- | --- |
| `identity.two-factor.login` | `GET` | `auth/two-factor-challenge` | `web`, `guest:identity` |
| `identity.two-factor.login.factor` | `GET` | `auth/two-factor-challenge/factor/{provider}/{enrollment?}` | `web`, `guest:identity` |
| `identity.two-factor.login.options` | `GET` | `auth/two-factor-challenge/options` | `web`, `guest:identity`, `throttle:5,1` |
| `identity.two-factor.login.store` | `POST` | `auth/two-factor-challenge` | `web`, `guest:identity`, `throttle:5,1` |

`GET identity.two-factor.login.options` issues the pending factor's challenge: the private
half is persisted in the session for the verification step, the public half (for WebAuthn,
the browser request options) is returned as JSON. Issuance and verification are separate
requests by design — each verification attempt consumes the stored state and needs a fresh
challenge.

`GET identity.two-factor.login` renders through the bound `TwoFactorChallengeView` contract, or
redirects to `identity.login` if there is no pending challenge on the session. The
`TwoFactorChallengePrompt` passed to the view carries the active `factor` key plus
`availableFactors` — every challengeable enrollment the user could switch to — so the view
can offer a method picker.

`GET identity.two-factor.login.factor` switches the pending challenge to another of the
user's challengeable factors (e.g. from `totp` to `webauthn`) — the provider's first
enrollment, or a specific one when the optional `enrollment` id is given. The target must be
a confirmed, non-backup enrollment of a provider listed in `challenge_providers`; anything
else is silently ignored. Switching discards any previously issued challenge state.

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
[provider-keyed endpoints](#provider-keyed-enrollment) below. The former TOTP-specific
routes (`identity.two-factor.qr-code`, `identity.two-factor.secret-key`,
`identity.two-factor.recovery-codes`, `identity.two-factor.regenerate-recovery-codes`) have
been removed: the QR code and secret are part of the TOTP begin-enrollment metadata, and
recovery codes are read and regenerated by enrolling the `recovery_code` provider.

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
when the last challengeable factor is revoked. Unknown provider keys return `404`.

The `webauthn` provider maps its two-step ceremony onto these endpoints: begin returns a
synthetic `pending` enrollment whose metadata carries the WebAuthn creation options (the
serialized options are parked in the session), confirm takes the browser's attestation as
`credential` and stores the passkey, and revoke deletes a passkey by id (or discards the
pending ceremony for the `pending` id). The [passkey routes](#passkey-management) offer the
same registration as a standalone browser ceremony.

### Passkey management

Passkey registration, listing, and removal all run through the generic
[provider-keyed endpoints](#provider-keyed-enrollment) with `provider=webauthn` — there are
no separate registration routes. What remains from `laravel/passkeys` are the
password-confirmation ceremony endpoints, which require only an authenticated session —
they *are* a password-confirmation mechanism, so they cannot demand a prior confirmation
themselves (both carry `throttle:5,1`):

| Route name | Verb | Path |
| --- | --- | --- |
| `identity.passkey.confirm-options` | `GET` | `auth/passkeys/confirm/options` |
| `identity.passkey.confirm` | `POST` | `auth/passkeys/confirm` |

(Passkey *login* — the passwordless sign-in path — lives on the [login page](/auth/login/).)

## Configuration

```php
'two_factor' => [
    'challenge_providers' => ['totp', 'webauthn'], // which providers are offered at the login challenge
    'secret_length' => 16,                         // TOTP secret length
    'window' => 1,                                 // accepted TOTP time-step window
    'recovery_codes' => 8,                         // how many recovery codes are generated
],
```

With `webauthn` in `challenge_providers` (the default), a password login by a user who owns a
passkey is challenged with that passkey as second factor. Set the list to `['totp']` to
restore challenge-on-TOTP-only behavior.

The WebAuthn challenge issues assertion options for all of the user's passkeys, and any of
them satisfies it — the active enrollment only determines what the challenge view displays.

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
