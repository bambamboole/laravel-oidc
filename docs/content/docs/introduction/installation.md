---
title: Installation
description: Install laravel-oidc, publish its migrations, and generate signing keys.
---

## Requirements

- PHP `^8.4`
- Laravel 13

## Install

```bash
composer require bambamboole/laravel-oidc
```

The service provider is auto-discovered.

## Publish and run the migrations

The package ships migrations for its own tables (clients, access tokens, refresh tokens,
authorization codes, consents, signing keys, authentication contexts, TOTP factors, recovery
codes, sessions, session participants, social accounts). Every package table uses a UUID (v7, time-ordered) primary key, and the user
references (`user_id`, `uuidMorphs` on `authenticatable`) are native `uuid` columns — **your
user model must be UUID-keyed** (e.g. `HasUuids`). The `laravel/passkeys` migration derives its
`user_id` type from your user model automatically; publish it if you also want to change that
table's own primary key.

```bash
php artisan vendor:publish --tag=oidc-migrations
php artisan migrate
```

If you use the passkey/WebAuthn factor, also publish the `laravel/passkeys` migration with
`php artisan vendor:publish --tag=passkeys-migrations`.

## Generate the signing key

Tokens are signed with RS256. The keypair lives in the `oidc_signing_keys` table you just
migrated, with the private key encrypted at rest; generate the first one with:

```bash
php artisan oidc:rotate-keys --if-missing
```

See [Key rotation](/provider/key-rotation/) for rotating it later and for a custom store.

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
