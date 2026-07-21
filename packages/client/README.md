# laravel-oidc-client

An OpenID Connect (OIDC) **relying party** for Laravel — log your users in through
any OIDC provider using the Authorization Code + PKCE flow, with strict `id_token`
validation against the provider's JWKS.

This is the client-side companion to
[`bambamboole/laravel-oidc`](https://github.com/bambamboole/laravel-oidc) (the OIDC
provider). The two are deliberately separate packages: an app that only needs to
*consume* an identity provider should not pull in a full OAuth2 authorization server,
TOTP, QR codes, and WebAuthn. Point this package at your own `laravel-oidc` provider
for self-SSO, or at any third-party IdP (Keycloak, Auth0, Okta, …).

📖 **[Read the documentation →](https://bambamboole.github.io/laravel-oidc/client/overview/)**

## What you get

- **Authorization Code + PKCE** login against any spec-compliant provider, with one-time
  `state`/`nonce` and single-use callback context.
- **Discovery-driven setup** — endpoints and JWKS come from
  `/.well-known/openid-configuration`, cached, with automatic JWKS refresh on unknown
  `kid` so provider key rotation just works.
- **Strict `id_token` validation** — RS256 signature, `iss`, `aud`, `azp`, `nonce`, and
  `exp`/`nbf`/`iat` with configurable leeway.
- **A user-resolution seam** — `OidcClient::resolveUsersUsing(...)` maps token claims to
  your user model; the default resolves the guard provider by `sub`.
- **RP-initiated logout** to the provider's end-session endpoint with `id_token_hint`.
- **Back-channel logout** (opt-in) — accepts provider-pushed logout tokens and tears down
  the matching local session immediately (server-side session drivers) or on the next
  request (enforcement middleware).

## Requirements

- PHP `^8.4`
- Laravel 11, 12, or 13

## Installation

```bash
composer require bambamboole/laravel-oidc-client

# Optional: publish the config
php artisan vendor:publish --tag=oidc-client-config
```

The service provider is auto-discovered. The relying party is off until enabled:

```dotenv
OIDC_RP_ENABLED=true
OIDC_RP_ISSUER=https://id.example.com
OIDC_RP_CLIENT_ID=...
OIDC_RP_CLIENT_SECRET=...   # optional — omit for a public client
OIDC_RP_REDIRECT_URI=https://app.example.com/login/callback
```

See the **[docs](https://bambamboole.github.io/laravel-oidc/client/overview/)** for the
full walkthrough, every config key, and the back-channel logout setup.

## Documentation

The documentation lives in [`docs/content/`](docs/content/) as Starlight-flavoured
Markdown. It is not built here: the
[`laravel-oidc` docs site](https://bambamboole.github.io/laravel-oidc) fetches these pages
on deployment and publishes them under `/client/`.

## Development

```bash
composer install
composer check   # pint --test, phpstan, pest
```

Tests run in isolation through Orchestra Testbench — no external OIDC provider is
required.

## License

MIT.
