---
title: Installation
description: Install laravel-oidc, publish its migrations, and generate signing keys.
---

## Requirements

- PHP `^8.4`
- Laravel 11, 12, or 13
- `laravel/passport` `^13.4` — the OAuth2 core the package builds on (installed as a dependency)

## Install

```bash
composer require bambamboole/laravel-oidc
```

The service provider is auto-discovered.

## Publish and run the migrations

The package ships migrations that extend `oauth_clients` (post-logout redirect URIs, exchange
audiences, provisioning key, back-channel logout) and add its own tables (authentication
contexts, access-token contexts, TOTP factors, recovery codes, sessions, session participants).
The publish tag also includes the `laravel/passkeys` migration.

```bash
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate
```

## Generate signing keys

Tokens are signed with RS256. Generate an env-based keypair — it keeps keys out of the
filesystem and manages rotation for you (see [Key rotation](/provider/key-rotation/)):

```bash
php artisan oidc:rotate-keys
```

This writes `OIDC_PRIVATE_KEY` and `OIDC_PUBLIC_KEY` to your `.env` (pass `--print` to emit
them to stdout for a secrets manager instead).

:::note
File-based keys work too: keys generated with Passport's `php artisan passport:keys` (or set
via `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY`) are picked up as a fallback whenever the
`OIDC_*` variables are unset.
:::

## Publish the config (optional)

```bash
php artisan vendor:publish --tag=oidc-config
```

This writes `config/oidc.php`. See [Configuration](/introduction/configuration/) for every key.

## Set the issuer

Set `OIDC_ISSUER` to the public origin of your provider (it falls back to `app.url` when unset).
Every URL advertised in the discovery document is derived from this origin, not the incoming
request's host:

```dotenv
OIDC_ISSUER=https://id.example.com
```

## Next steps

- If you only want the **OIDC provider**, register a consent view
  (see [Endpoints & discovery](/provider/endpoints/)) and you are ready to authorize clients.
- If you want the **auth engine** too, bind your login/registration views and a create-user
  action — see [Auth engine overview](/auth/overview/).
