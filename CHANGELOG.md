# Changelog

All notable changes to `bambamboole/laravel-oidc` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) (pre-1.0: minor versions may carry
breaking changes).

## [Unreleased]

### Added

- **Discovery document completeness:** `/.well-known/openid-configuration` now advertises
  `client_credentials` in `grant_types_supported`, `response_modes_supported: ["query"]`,
  `claims_parameter_supported: false`, `request_parameter_supported: false`,
  `request_uri_parameter_supported: false`, and
  `introspection_endpoint_auth_methods_supported` / `revocation_endpoint_auth_methods_supported`.
  All advertised endpoint URLs are now derived from the configured `issuer` origin rather than
  the incoming request.
- **Configurable route prefix:** the `/oauth/*` routes this package registers now honour
  `config('passport.path', 'oauth')` instead of hardcoding `oauth`.
- **`oidc.api_guard` config** (`OIDC_API_GUARD`, default `api`): the guard the userinfo
  endpoint authenticates against, previously hardcoded to `api`.
- **`oidc:rotate-keys` command:** generates a new RSA signing keypair and writes
  `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY`, and `OIDC_PREVIOUS_PUBLIC_KEY` into `.env`
  (or, with `--print`, to stdout for a secrets manager), rolling the current public key into
  `OIDC_PREVIOUS_PUBLIC_KEY`. That previous key is served in JWKS via
  `config('oidc.additional_public_keys')` (deduplicated by `kid`) so tokens signed before the
  rotation keep validating until they expire; remove it once they have. Keys live entirely in
  env variables — no key files, no database.

### Fixed

- **PKCE required for all clients** (OAuth 2.1 §4.1.1/§7.6): the authorization endpoint now
  rejects any authorization request missing a `code_challenge`, for confidential clients too,
  not only public ones.
- **RFC-shaped error responses:** the userinfo endpoint and the `CheckAudience` middleware now
  return RFC 6750 bearer-token errors (`WWW-Authenticate: Bearer error="..."` plus a JSON
  `{"error": "invalid_token"}` / `{"error": "insufficient_scope"}` body) instead of bare
  `401`/`403` aborts; introspection and revocation now return RFC 6749 §5.2-shaped
  `{"error": "invalid_client"}` JSON bodies (still `401` with `WWW-Authenticate: Basic`) instead
  of a bare string/no body.
- **Chained token exchange nests `act`** (RFC 8693 §4.1): exchanging an already-exchanged token
  now nests the prior `act` claim (`act.act`) instead of overwriting it, preserving the full
  actor chain.

### Changed

- Internal dedup: `AccessTokenHookRunner` and `IdTokenResponse` now share a single
  `ResolvesRequestGrantType` trait, and `CheckAudience` verifies the token's signature once
  and reuses the parsed result instead of re-parsing it to look up the token record.

## [0.1.0]

Initial release — an OpenID Connect provider layer on top of Laravel Passport 13.

### Added

- **OIDC core:** RS256 `id_token`s (with `at_hash`, `auth_time`, `azp`, `nonce`), a
  `/.well-known/openid-configuration` discovery document, a `/.well-known/jwks.json` endpoint
  (RFC 7638 `kid`s, phpseclib-based multi-format key parsing), a `userinfo` endpoint,
  RP-initiated logout (`/oauth/logout`), RFC 7662 introspection, and RFC 7009 revocation.
- **Full `/oauth/*` route ownership** via `Passport::ignoreRoutes()`, with a required
  `Passport::authorizationView()` consent view and `max_age` / `prompt` handling.
- **RFC 9068 access tokens:** access tokens are `application/at+jwt` JWTs carrying `iss`, `aud`,
  `sub`, `client_id`, `iat`, `nbf`, `exp`, `jti`, and a space-delimited `scope` (the legacy
  `scopes` array is retained for Passport's guard).
- **Claim hooks:** per-trigger registration (`Oidc::onPostLogin`, `onRefresh`,
  `onClientCredentials`, `onTokenExchange`, `onUserinfo`) with per-artifact writers and a
  protected-claim blocklist. Hooks must be pure claim writers.
- **RFC 8693 token exchange:** the `urn:ietf:params:oauth:grant-type:token-exchange` grant
  (confidential clients only, gated by the client's `grant_types` and an
  `allowed_exchange_audiences` allowlist), a swappable `ExchangePolicy` (audience reciprocity,
  target allowlist, monotonic scope narrowing, same-subject, lifetime cap, `act` delegation),
  and a self-contained `CheckAudience` resource-server middleware.
- **Session-token issuance:** a `SessionTokenProvider` seam (default mints a first-party root
  token at login) and `Oidc::issueScopedToken($audience, $scopes)` to derive short-lived,
  audience-scoped browser tokens — designed to power browser-direct data fetching.
- Extension contracts: `ScopeRepository`, `ClaimsResolver`, `ExchangePolicy`,
  `SessionTokenProvider`.

### Notes

- Requires PHP `^8.4`, `laravel/passport ^13.4`, `lcobucci/jwt ^5`, `phpseclib/phpseclib ^3.0.15`.
- The GitHub Actions test workflow (`.github/workflows/tests.yml`) runs the suite across
  Laravel 11/12/13 on the standalone repository.
- Known limitations (see the README): no back-channel logout, no `acr`/`amr` claims, refresh
  `id_token`s omit `auth_time`, and revoking a subject token does not cascade to already-issued
  exchanged tokens.

[Unreleased]: https://github.com/bambamboole/laravel-oidc/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/bambamboole/laravel-oidc/releases/tag/v0.1.0
