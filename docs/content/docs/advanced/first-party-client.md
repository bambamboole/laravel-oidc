---
title: First-party client provisioning
description: Provision the package-managed first-party confidential client with the oidc:client command or Oidc::provisionFirstPartyClient().
---

The **first-party client** is a confidential OAuth client the package manages on your
behalf. It is not owned by any user, and it is the client that mints the session root
token and performs token exchanges for the [browser-fetch flow](/advanced/browser-fetch/).
Its id belongs in `config('oidc.first_party.client_id')` (`OIDC_FIRST_PARTY_CLIENT`).

Provisioning is **idempotent**: the client is identified by an internal provisioning
key (`first-party`) stored on `oauth_clients.oidc_provisioning_key`. Running the
provisioner again reconciles the existing client's metadata rather than creating a
duplicate. The whole operation runs inside a database transaction with a
`lockForUpdate()` on the keyed row, so concurrent runs cannot race.

## The `oidc:client` command

```bash
php artisan oidc:client --first-party \
    --name="My App" \
    --redirect-uri=https://app.test/callback \
    --post-logout-redirect-uri=https://app.test/ \
    --audience=https://api.orders.test
```

| Option | Purpose |
| --- | --- |
| `--first-party` | **Required.** Provision the package-managed first-party client. |
| `--name=` | Client display name. Prompted for interactively if omitted. |
| `--redirect-uri=*` | Registered authorization callback URI. Repeatable. At least one is required. Prompted for interactively if omitted. |
| `--post-logout-redirect-uri=*` | Registered post-logout redirect URI. Repeatable. |
| `--audience=*` | Allowed token-exchange audience. Repeatable. Adding any enables the token-exchange grant on the client. |
| `--trusted` | Mark the first-party client as trusted (skips consent). |
| `--adopt=` | Adopt an existing Passport client id under the first-party provisioning key. |
| `--rotate` | Rotate the client secret explicitly. |
| `--write-env` | Write `OIDC_FIRST_PARTY_CLIENT` and `OIDC_FIRST_PARTY_TRUSTED` to `.env`. |

The command prints the resulting `OIDC_FIRST_PARTY_CLIENT` / `OIDC_FIRST_PARTY_TRUSTED`
values, plus `OIDC_RP_CLIENT_ID` / `OIDC_RP_CLIENT_SECRET` when a secret was issued or
rotated (the plaintext secret is only ever available at that moment).

## `Oidc::provisionFirstPartyClient()`

The same operation is available programmatically:

```php
use Bambamboole\LaravelOidc\Facades\Oidc;

$result = Oidc::provisionFirstPartyClient(
    name: 'My App',
    redirectUris: ['https://app.test/callback'],
    postLogoutRedirectUris: ['https://app.test/'],
    allowedExchangeAudiences: ['https://api.orders.test'],
    adoptClientId: null,
    rotateSecret: false,
);
```

It returns a `FirstPartyClientProvisioningResult`:

```php
final readonly class FirstPartyClientProvisioningResult
{
    public Client $client;                             // the Passport client model
    public string $clientId;
    public ?string $clientSecret;                      // plaintext, only when newly created or rotated
    public FirstPartyClientProvisioningOutcome $outcome;
}
```

`$outcome` is one of:

| Outcome | Meaning |
| --- | --- |
| `Created` | A new client was created; `clientSecret` holds its plaintext secret. |
| `Reconciled` | An existing first-party client's metadata was updated in place; `clientSecret` is `null`. |
| `Rotated` | `rotateSecret: true` was passed; the secret was regenerated and `clientSecret` holds the new plaintext value. |

## Eligibility and validation

An adopted or existing client must pass the eligibility checks, otherwise a
`FirstPartyClientProvisioningException` is thrown: it must **not be revoked**, must be
**confidential**, and must **not be owned by a user** (first-party). A different client
already holding the `first-party` provisioning key cannot be overwritten by an
`--adopt` of another id.

The provisioner validates and normalizes its URI inputs (deduplicating them):

- **Redirect URIs** and **post-logout redirect URIs** must each be an absolute
  **HTTP(S)** URI with a non-empty host, **no** user-information component, and **no**
  fragment. Control characters, backslashes, and malformed percent-escapes are
  rejected.
- **Exchange audiences** must be an absolute URI identifier — an `http`/`https` URL
  with a host, a syntactically valid `urn:` NID:NSS, or another absolute-scheme URI.
  Configuring any audience while `oidc.token_exchange.enabled` is `false` is rejected.
- Every metadata value must be a non-empty string; the name must not be empty and at
  least one redirect URI is required.
