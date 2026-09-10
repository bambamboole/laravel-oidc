---
title: Dynamic client registration & MCP
description: RFC 7591 dynamic client registration and the RFC 8414/9728 discovery documents MCP clients use to connect.
---

The provider can let OAuth clients register themselves at runtime (RFC 7591) and advertise
the discovery documents resource servers and their clients need to find it (RFC 8414
authorization server metadata, RFC 9728 protected resource metadata). Together these enable
the connect flow MCP clients such as Claude or Cursor drive:

1. The client calls the protected resource (e.g. `POST /mcp`) without a token and receives a
   `401` whose `WWW-Authenticate` header points at the resource metadata.
2. `GET /.well-known/oauth-protected-resource/mcp` names this provider as the authorization
   server.
3. `GET /.well-known/oauth-authorization-server` (or `/.well-known/openid-configuration` —
   both serve the same document) lists the authorize, token, and registration endpoints.
4. The client registers itself via `POST /oauth/register` and receives a `client_id`.
5. A standard authorization-code flow with PKCE (`S256`, enforced for every client) yields
   the access token the client then presents to the resource.

## Protected resource metadata (RFC 9728)

Declare the resources this provider protects in `oidc.protected_resources`, keyed by the
resource's path relative to the issuer origin:

```php
'protected_resources' => [
    'mcp' => ['scopes' => ['mcp:use']],
],
```

`GET /.well-known/oauth-protected-resource/mcp` then serves:

```json
{
    "resource": "https://id.example.com/mcp",
    "authorization_servers": ["https://id.example.com"],
    "scopes_supported": ["mcp:use"],
    "bearer_methods_supported": ["header"]
}
```

The `resource` value is built from `oidc.issuer` (falling back to `app.url`), never from the
request host — strict clients compare it byte-for-byte against the URL they derived the
metadata from. Paths that are not configured return `404`; an empty-string key serves the
issuer root itself as the resource.

## Authorization server metadata (RFC 8414)

`GET /.well-known/oauth-authorization-server` serves the same document as
`/.well-known/openid-configuration` (RFC 8414 permits the additional OIDC members). The
path-insertion form `/.well-known/oauth-authorization-server/{path}` is also routed; with realms
routed by path the realm follows the well-known segment
(`/.well-known/oauth-authorization-server/realms/{realm}`). When
dynamic client registration is enabled, both documents advertise the
`registration_endpoint`.

## Dynamic client registration (RFC 7591)

Registration is disabled by default. Enable and scope it via `oidc.clients.registration`:

```php
'dcr' => [
    'enabled' => env('OIDC_DCR_ENABLED', false),
    'allowed_redirect_schemes' => [],       // e.g. ['claude', 'cursor', 'vscode']
    'allowed_redirect_domains' => ['*'],    // exact hosts, or '*' for any
    'default_scopes' => [],                 // e.g. ['mcp:use'] — restricts registered clients
],
```

`POST /oauth/register` registers an authorization-code client from these RFC 7591
and OIDC metadata fields; every other field (`application_type`, `software_id`, and whatever
else MCP clients send) is ignored:

| Field | Default | Rule |
| --- | --- | --- |
| `redirect_uris` | required | Absolute, no user-info or fragment; `http(s)` with a host in `allowed_redirect_domains`, or a scheme in `allowed_redirect_schemes` (non-HTTP schemes still need a host, so `cursor://anysphere.cursor-retrieval/…` passes while `cursor:/callback` is rejected). |
| `client_name` | first redirect host | Trimmed. |
| `token_endpoint_auth_method` | `none` | `none` registers a public client. `client_secret_basic` or `client_secret_post` registers a confidential client; the response then carries `client_secret` (returned once) and `client_secret_expires_at: 0`. Anything else is rejected. |
| `grant_types` | `["authorization_code", "refresh_token"]` | Must include `authorization_code` and may only add `refresh_token`. |
| `response_types` | `["code"]` | Must be exactly `["code"]`. |
| `post_logout_redirect_uris` | `[]` | Validated like `redirect_uris`; honoured by [RP-initiated logout](/provider/logout/). |
| `backchannel_logout_uri` | none | Absolute `https` URL without a fragment — see [Back-channel logout](/provider/logout/#back-channel-logout). |
| `backchannel_logout_session_required` | `false` | Stored as given. |

PKCE is enforced by the grant for every client. The endpoint is unauthenticated (as the RFC and
MCP clients expect) but throttled; keep the redirect allowlists as tight as your clients allow.

When `default_scopes` is non-empty the registered client is restricted to those scopes via
the client's `scopes` column; an empty list leaves the client unrestricted. A successful
registration returns `201` echoing every stored value (RFC 7591 §3.2.1):

```json
{
    "client_id": "01hf…",
    "client_id_issued_at": 1765465200,
    "client_name": "Claude",
    "redirect_uris": ["https://claude.ai/api/mcp/auth_callback"],
    "post_logout_redirect_uris": [],
    "grant_types": ["authorization_code", "refresh_token"],
    "response_types": ["code"],
    "token_endpoint_auth_method": "none",
    "scope": "mcp:use"
}
```

`backchannel_logout_uri` and `backchannel_logout_session_required` appear when a back-channel
URI was registered. Validation failures return `400` with an RFC 7591 error body:
`invalid_redirect_uri` for a rejected redirect URI, `invalid_client_metadata` for everything else.

## Wiring a Laravel MCP server

For an app that serves its MCP endpoint with [laravel/mcp](https://github.com/laravel/mcp)
and authenticates it through this provider's guard, two pieces of app-side wiring complete
the flow:

- Configure the resource: `'protected_resources' => ['mcp' => ['scopes' => [/* … */]]]` and
  enable `oidc.clients.registration`.
- laravel/mcp's `AddWwwAuthenticateHeader` middleware only emits the `resource_metadata`
  pointer on 401 responses when a route named `mcp.oauth.protected-resource.nested` exists.
  Alias it onto this package's controller in your routes file:

```php
use Bambamboole\LaravelOidc\Server\Protocol\Http\Controllers\ProtectedResourceController;

Route::get('/.well-known/oauth-protected-resource/{path}', ProtectedResourceController::class)
    ->where('path', '.*')
    ->name('mcp.oauth.protected-resource.nested');
```
