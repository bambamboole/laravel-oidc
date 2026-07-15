---
title: Installation
description: Install laravel-oidc-client, enable the relying party, and register it at your provider.
sidebar:
  order: 2
---

## Requirements

- PHP `^8.4`
- Laravel 11, 12, or 13

There is no database migration and no key material to generate — the client verifies
tokens against the provider's published JWKS.

## Install

```bash
composer require bambamboole/laravel-oidc-client

# Optional: publish the config
php artisan vendor:publish --tag=oidc-client-config
```

The service provider is auto-discovered.

## Enable and point at your provider

The relying party is **off by default** — no routes are registered until you enable it:

```dotenv
OIDC_RP_ENABLED=true
OIDC_RP_ISSUER=https://id.example.com
OIDC_RP_CLIENT_ID=...
OIDC_RP_CLIENT_SECRET=...
OIDC_RP_REDIRECT_URI=https://app.example.com/login/callback
```

`OIDC_RP_CLIENT_SECRET` is optional — leave it unset for a public client; the flow is
protected by PKCE either way.

Everything else (authorization endpoint, token endpoint, JWKS) is discovered from the
issuer's `/.well-known/openid-configuration`.

## Register the client at the provider

At your provider, register a client with the same `client_id` and the exact
`redirect_uri`. If the provider is your own `laravel-oidc` instance, the
[`oidc:client` command](/advanced/first-party-client/) provisions one and prints the
matching `OIDC_RP_CLIENT_ID` / `OIDC_RP_CLIENT_SECRET` values.

## Next steps

- Wire up how a token subject becomes a local user — see
  [Login & logout](/client/login-and-logout/).
- Opt into provider-pushed session teardown — see
  [Back-channel logout](/client/backchannel-logout/).
