---
title: Client administration API
description: Manage OAuth clients over an authenticated REST API built for infrastructure as code.
---

The provider can expose a small REST API for managing OAuth clients, so clients are declared
in infrastructure as code (a Pulumi or Terraform provider, Ansible, plain `curl`) instead of
being created by hand. It complements the two existing ways of creating clients:

| Need | Use |
| --- | --- |
| Self-service registration by untrusted clients, such as MCP clients | [Dynamic client registration](/provider/dynamic-client-registration/) (public clients, no credentials) |
| The one client the provider itself uses for browser-fetch and self SSO | [First-party client provisioning](/advanced/first-party-client/) |
| Every other client, managed by an operator or a deployment pipeline | **This API** |

Clients created here are always **confidential** (they receive a secret) and owner-less.
The API never touches user-owned Passport clients, and it treats a revoked client as gone.

## Enabling

```php
// config/oidc.php
'admin' => [
    'enabled' => env('OIDC_ADMIN_ENABLED', false),
    'scope' => 'oidc:admin',
],
```

With `OIDC_ADMIN_ENABLED=true` the six `oidc.admin.clients.*` [route handlers](/introduction/route-handlers/)
are registered under `oauth/admin/clients` and the `oidc:admin` scope becomes known to the
provider. The scope is hidden: it never appears in discovery or on a consent screen. While
the feature is disabled the routes do not exist and the scope is unknown, so no admin token
can be issued.

## Bootstrapping the admin client

Callers authenticate as an OAuth client. The first one is created by an artisan command,
which is idempotent: re-running it reconciles the existing client instead of creating another.

```bash
php artisan oidc:admin-client --name="Pulumi"
# OIDC_ADMIN_CLIENT_ID=9d3f6c1e-2b4a-4e0f-9a7b-0c1d2e3f4a5b
# OIDC_ADMIN_CLIENT_SECRET=...   printed only when created or rotated

php artisan oidc:admin-client --rotate   # issue a new secret
```

The command creates a confidential, owner-less `client_credentials` client whose `scopes`
column lists the admin scope. It is marked with the internal provisioning key `admin`, which
makes it read-only for the API itself: it can rotate its own secret, but it cannot modify or
revoke itself. Create further admin clients (one per stack, for instance) through the API by
giving them `grant_types: ["client_credentials"]` and `scopes: ["oidc:admin"]`.

### Who can obtain the admin scope

The scope is finalized only for the `client_credentials` grant, and only when the requesting
client is confidential, not owned by a user, and lists the scope **explicitly** in its
`scopes` column. A client with an unrestricted (`null`) scopes column or the `*` wildcard
never receives it, and interactive grants strip it, so enabling the feature never grants
administrative access to a pre-existing client.

## Authentication

Request a token with the client credentials grant and send it as a bearer token:

```bash
curl -s https://id.example.com/oauth/token \
  -d grant_type=client_credentials \
  -d client_id="$OIDC_ADMIN_CLIENT_ID" \
  -d client_secret="$OIDC_ADMIN_CLIENT_SECRET" \
  -d scope=oidc:admin
```

The API verifies the bearer itself; it must be this issuer's `at+jwt`, unexpired, unrevoked,
carry **no user**, and be addressed to the issuer. Then the token must hold the admin scope
(`*` does not count) and its client must still list the scope. Failures follow RFC 6750:

| Condition | Status | Body |
| --- | --- | --- |
| Missing, malformed, expired, or revoked token; a user-bound token; a token addressed to another resource | `401` | `{"error": "invalid_token"}` + `WWW-Authenticate` |
| A valid client token without the admin scope, or whose client no longer lists it | `403` | `{"error": "insufficient_scope"}` + `WWW-Authenticate` |

## Endpoints

| Verb | Path | Route name | Success |
| --- | --- | --- | --- |
| `GET` | `/oauth/admin/clients` | `oidc.admin.clients.index` | `200`, cursor paginated |
| `POST` | `/oauth/admin/clients` | `oidc.admin.clients.store` | `201`, includes `client_secret` |
| `GET` | `/oauth/admin/clients/{client}` | `oidc.admin.clients.show` | `200` |
| `PATCH` | `/oauth/admin/clients/{client}` | `oidc.admin.clients.update` | `200` |
| `DELETE` | `/oauth/admin/clients/{client}` | `oidc.admin.clients.destroy` | `204` |
| `POST` | `/oauth/admin/clients/{client}/secret` | `oidc.admin.clients.secret` | `200`, includes `client_secret` |

Every endpoint is throttled and honours `oidc.routes.prefix`, `oidc.routes.middleware`, and
per-handler `oidc.handlers` overrides like any other handler. The endpoints are not advertised
in the discovery document.

The normative contract is the OpenAPI 3.1 document shipped with the package at
[`resources/openapi/client-administration.json`](https://github.com/bambamboole/laravel-oidc/blob/main/packages/server/resources/openapi/client-administration.json).
It is generated from the code and kept in sync by the test suite (`composer openapi`
regenerates it), so a provider can pin the document of the release it targets.

## The client representation

Field names follow RFC 7591 where one exists, with `scopes` as an array.

```json
{
  "client_id": "9d3f6c1e-2b4a-4e0f-9a7b-0c1d2e3f4a5b",
  "client_secret": "only present in the create and rotate responses",
  "client_name": "Orders Web",
  "confidential": true,
  "grant_types": ["authorization_code", "refresh_token"],
  "redirect_uris": ["https://orders.example.com/auth/callback"],
  "post_logout_redirect_uris": ["https://orders.example.com/"],
  "backchannel_logout_uri": "https://orders.example.com/auth/backchannel-logout",
  "backchannel_logout_session_required": true,
  "scopes": ["openid", "profile", "email"],
  "allowed_exchange_audiences": [],
  "trusted": false,
  "created_at": "2026-09-08T10:12:41+00:00",
  "updated_at": "2026-09-08T10:12:41+00:00"
}
```

| Field | Writable | Rules |
| --- | --- | --- |
| `client_id` | no | Server-generated UUID, never reused. |
| `client_secret` | no | Plaintext secret, only in the `201` create and `200` rotate responses. Never readable again. |
| `client_name` | required on create | 1 to 255 characters, trimmed. |
| `confidential` | no | Always `true` for API-created clients; `false` only for dynamically registered public clients. |
| `grant_types` | required on create | Non-empty subset of `authorization_code`, `refresh_token`, `client_credentials`, `urn:ietf:params:oauth:grant-type:token-exchange`. `refresh_token` needs `authorization_code`; `authorization_code` needs a redirect URI; token exchange needs an audience and `oidc.token_exchange.enabled`. |
| `redirect_uris`, `post_logout_redirect_uris` | yes, default `[]` | Absolute `http(s)` URIs with a host, no user info, no fragment. Trimmed and deduplicated, order preserved. |
| `backchannel_logout_uri` | yes, default `null` | Same URI rules, or `null`. |
| `backchannel_logout_session_required` | yes, default `false` | |
| `scopes` | yes, default `null` | `null` leaves the client unrestricted (Passport's default); a list restricts it to those scopes, every entry must exist in the [scope catalog](/provider/scopes-and-claims/); `[]` allows no scope at all. `*` is rejected. The admin scope additionally requires `client_credentials`. |
| `allowed_exchange_audiences` | yes, default `[]` | `http(s)` URLs or `urn:` identifiers; requires `oidc.token_exchange.enabled`. Governs token exchange and `resource`-bound client credentials tokens. |
| `trusted` | yes, default `false` | Skips the consent prompt, like `oidc.trusted_clients`. Only for confidential clients. |
| `created_at`, `updated_at` | no | RFC 3339 timestamps. |

Values are normalized before they are stored and echoed back exactly as stored, so what a
provider reads after `GET` equals what the server holds.

## Examples

Create a web client:

```bash
curl -s https://id.example.com/oauth/admin/clients \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{
    "client_name": "Orders Web",
    "grant_types": ["authorization_code", "refresh_token"],
    "redirect_uris": ["https://orders.example.com/auth/callback"],
    "scopes": ["openid", "profile", "email"]
  }'
```

Create a machine-to-machine client restricted to one API scope:

```bash
curl -s https://id.example.com/oauth/admin/clients \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"client_name": "Billing worker", "grant_types": ["client_credentials"], "scopes": ["billing:write"]}'
```

Update only the fields you send; an empty object is a no-op:

```bash
curl -s -X PATCH https://id.example.com/oauth/admin/clients/$CLIENT_ID \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"trusted": true}'
```

Rotate the secret (tokens issued with the old secret stay valid) and revoke a client
(revokes every token issued to it):

```bash
curl -s -X POST https://id.example.com/oauth/admin/clients/$CLIENT_ID/secret -H "Authorization: Bearer $TOKEN"
curl -s -X DELETE https://id.example.com/oauth/admin/clients/$CLIENT_ID -H "Authorization: Bearer $TOKEN"
```

## Errors

| Condition | Status | Body |
| --- | --- | --- |
| Body is not a JSON object | `400` | `{"message": "…"}` |
| Unknown, revoked, or user-owned client id (also `DELETE` on an already revoked client) | `404` | `{"message": "…"}` |
| Validation failure, an unknown field, or `client_secret` in the body | `422` | `{"message": "…", "errors": {"field": ["…"]}}` |
| `PATCH`/`DELETE` on a client managed by an artisan command, revoking the acting client itself, or rotating the secret of a public client | `409` | `{"message": "…"}` |
| Too many requests | `429` | Laravel's throttle response |

Unknown fields are rejected on purpose: a misspelt key in a provider or a version skew between
provider and server fails loudly instead of silently ignoring configuration. The read-only
fields `client_id`, `confidential`, `created_at`, and `updated_at` are ignored in a body, so a
fetched representation can be sent back.

## Listing and pagination

`GET /oauth/admin/clients?per_page=50` returns Laravel's paginated resource envelope:

```json
{
  "data": [ { "client_id": "…", "client_name": "…" } ],
  "links": { "first": null, "last": null, "prev": null, "next": "https://…?cursor=…" },
  "meta": { "path": "https://…/oauth/admin/clients", "per_page": 50, "next_cursor": "…", "prev_cursor": null }
}
```

`per_page` accepts 1 to 100 and defaults to 50. Follow `meta.next_cursor` (pass it as
`cursor`) until it is `null`. Revoked and user-owned clients are not listed.

## Using it from an infrastructure-as-code provider

- `client_id` is the resource identity; persist it from the `201` response immediately.
- Reconcile in the provider: read the client, diff the desired state against it, and send only
  the changed keys with `PATCH`.
- Treat `client_secret` as a secret output available at create and rotate time; model a
  rotation as an explicit action (a `secret_version` input, for example) that calls
  `POST …/secret`.
- `404` on read means the resource is gone; `404` on delete means it was already gone.
- `updated_at` is computed and should be ignored in diffs.
- Normalization is deterministic and order preserving: trim, deduplicate keeping the first
  occurrence, never sort. Normalize inputs the same way or treat the server value as canonical.

## Audit events

Every change is recorded through the [audit log](/provider/audit-logging/) with the acting
client id in `actor`: `admin.client.created`, `admin.client.updated` (with the list of
`changed` fields), `admin.client.secret_rotated`, and `admin.client.revoked`. The bootstrap
command records `admin.client.provisioned` with `provisioning_key: admin`. Secrets are never
part of an audit event.
