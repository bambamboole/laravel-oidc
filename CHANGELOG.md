# Changelog

All notable changes to `bambamboole/laravel-oidc` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) (pre-1.0: minor versions may carry
breaking changes).

## [Unreleased]

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

- Requires PHP `^8.4`, `laravel/passport ^13`, `lcobucci/jwt ^5`, `phpseclib/phpseclib ^3`.
- The GitHub Actions test workflow (`.github/workflows/tests.yml`) runs the suite across
  Laravel 11/12/13 on the standalone repository.
- Known limitations (see the README): no back-channel logout, no `acr`/`amr` claims, refresh
  `id_token`s omit `auth_time`, and revoking a subject token does not cascade to already-issued
  exchanged tokens.

[Unreleased]: https://github.com/bambamboole/laravel-oidc/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/bambamboole/laravel-oidc/releases/tag/v0.1.0
