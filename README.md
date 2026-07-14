# laravel-oidc-client

An OpenID Connect (OIDC) **relying party** for Laravel — log your users in through
any OIDC provider using the real Authorization-Code + PKCE flow, with strict
`id_token` validation against the provider's JWKS.

This is the client-side companion to
[`bambamboole/laravel-oidc`](https://github.com/bambamboole/laravel-oidc) (the OIDC
provider). The two are deliberately separate packages: an app that only needs to
*consume* an identity provider should not pull in a full OAuth2 authorization server,
TOTP, QR codes, and WebAuthn. Point this package at your own `laravel-oidc` provider
for self-SSO, or at any third-party IdP (Keycloak, Auth0, Okta, …).

> **Status:** scaffold. The relying-party module (discovery, PKCE, callback,
> `IdTokenValidator`, guard login) is being implemented as Phase 1b of the
> [self-SSO design](https://github.com/bambamboole/laravel-oidc/blob/main/docs/self-sso-and-auth-engine-design.md).

## Requirements

- PHP `^8.4`
- Laravel `^11.0 || ^12.0 || ^13.0`

## Installation

```bash
composer require bambamboole/laravel-oidc-client

# Optional: publish the config
php artisan vendor:publish --tag=oidc-client-config
```

The service provider is auto-discovered.

## Configuration

See `config/oidc-client.php`. Set the provider `issuer`, your registered
`client_id` / `client_secret` / `redirect_uri`, the `scopes` you request, and the
`login_guard` the resolved user is logged into.

## Back-channel logout

Back-channel logout is **opt-in**. Set `OIDC_RP_BACKCHANNEL_LOGOUT_ENABLED=true` to
register a `POST /oidc/backchannel-logout` endpoint that accepts
[OIDC back-channel logout tokens](https://openid.net/specs/openid-connect-backchannel-1_0.html)
pushed by the provider and tears down the matching local session.

You must register this endpoint as the RP's `backchannel_logout_uri`
(`<your-app-url>/oidc/backchannel-logout`) on the client at the provider — providers
only POST to URIs they know about.

For *immediate* teardown, back-channel logout needs:

- a **persistent cache store** (redis, database, or file — not `array`), because the
  endpoint records a session pointer and a revocation marker in cache; and
- a **server-side session driver** (database, redis, or file), so the endpoint can
  destroy the session directly through its handler.

With the cookie session driver there's no server-side session to destroy directly, so
teardown falls back to the revocation marker: the next request from that browser is
caught and logged out by the `EnforceBackchannelLogout` middleware instead of being
torn down immediately.

That middleware is auto-appended to the `web` middleware group whenever back-channel
logout is enabled. Set `oidc-client.backchannel_logout.auto_middleware` to `false` if
you'd rather register `oidc-client.enforce-logout` yourself (e.g. on a subset of
routes).

## Development

```bash
composer install
composer check   # pint --test, phpstan, pest
```

Tests run in isolation through Orchestra Testbench — no external OIDC provider is
required.

## License

MIT.
