---
title: Upgrading
description: Breaking changes per release and the migration steps for each.
---

## 0.10.x → 0.11.0

### Database

- Every package table now uses a UUID (v7) primary key, and user reference
  columns are native `uuid` columns. The shipped migrations changed in place:
  existing installs must re-run them (`migrate:fresh`) or convert the columns
  manually. The `sid` claim and all enrollment ids are now uuid-formatted
  strings.

### User model

- The `FactorAuthenticatable` contract is gone — factor storage is owned by
  the factor providers. Remove `implements FactorAuthenticatable` from your
  user model; keep the `HasAuthenticationFactors` trait.
- If your model still carries Fortify-era `two_factor_secret` /
  `two_factor_recovery_codes` columns, casts, or hidden entries, they are
  dead — the package stores factors in its own tables.

### Server API

- `Oidc::issuer()` → `Issuer::url()`. `Oidc::handlerConfig($handler)` →
  `$handler->config()`. The rest of the facade is unchanged, and the facade
  is now safe to resolve in a service provider's `boot()` — it no longer
  pulls the signing-key/encrypter graph until a method needs it.
- Custom factor providers: `FactorResponse` is gone — `verify()` receives the
  request input as a plain `array<string, mixed>`.
- Custom auth view bindings: `RegisterView::respond()` and
  `PasswordConfirmationView::respond()` no longer receive a prompt argument.
- The `EnvironmentStore` interface is gone — type-hint `EnvironmentFile`.
- Token-exchange audiences are accepted as `http(s)` URLs or any `urn:`
  string; the strict RFC 8141 URN validation was dropped.
- Testing: `AuthorizationCodeResult::json()` is gone — use
  `->response->json()`.

New APIs that replace common app-side workarounds:

- `FactorRegistry::hasChallengeableFactors($user)` — "does this user have a
  usable second factor?" Works across all providers; a
  `totpFactors()`-based check misses WebAuthn-only users.
- `oidc:rotate-keys --if-missing` — generate keys only when none are
  configured (idempotent, for provisioning scripts).
- `PasswordConfirmation::confirm($session)` /
  `PasswordConfirmation::confirmedRecently($session)` — the
  `auth.password_confirmed_at` session logic, shared with the package's own
  controllers.

### UI components

- `Tables\PasskeysTable` is gone — `Tables\TwoFactorMethodsTable` lists every
  enrolled factor (passkeys, TOTP, recovery codes) in one table.
- `PasskeyVerify::make()` takes `$label`/`$loadingLabel`/`$separator` as
  optional named arguments; the fluent setters are gone. New
  `PasskeyVerify::makeIfAvailable($optionsRoute, $submitRoute)` returns null
  when the passkey routes are not registered.

### Client

- The `oidc-client.handlers` and `oidc-client.routes.{prefix,middleware}`
  config keys are gone. The four relying-party routes (`login`,
  `login.callback`, `logout`, `oidc.backchannel-logout`) are registered
  as-is; `oidc-client.enabled => false` disables them all, and the
  back-channel route additionally requires
  `oidc-client.backchannel_logout.enabled`. `Client\Routing\Handler` is now
  only the registry of those route names; `HandlerConfig` and
  `HandlerRegistrar` are gone.
- `OidcClientFake::issuer()` and `forUser()` are gone — set
  `config('oidc-client.issuer')` before calling `OidcClient::fake()`, and use
  `loginAs($user)`.
