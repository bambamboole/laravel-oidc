---
title: Access tokens (RFC 9068)
description: The structured JWT access-token format the provider issues.
---

Access tokens issued by this package are structured JWTs per
[RFC 9068](https://www.rfc-editor.org/rfc/rfc9068) rather than opaque strings.

## Header

The JWT header carries `"typ": "at+jwt"` and a `kid` matching the JWKS endpoint, so a resource
server can select the right key to verify the signature.

## Claims

Standard claims: `iss`, `aud`, `sub`, `client_id`, `iat`, `nbf`, `exp`, `jti`, and a
space-delimited `scope` string (e.g. `"openid email"`).

The legacy `scopes` array claim (`["openid", "email"]`) is retained alongside `scope` for
compatibility with resource servers that read scopes from the token body as an array. This
package's own `auth:oidc` guard and userinfo endpoint don't depend on it; they read scopes off
the persisted token record instead. Both claims describe the same grant; `scope` is the RFC 9068
form and `scopes` is the compatibility form.

`aud` names the resources the token is for (RFC 9068 §2.2); the client it was issued to is in
`client_id`. A client asks for a resource with the RFC 8707 `resource` parameter at the
authorization endpoint (repeatable; the token endpoint may narrow it to a subset, and so may a
refresh) or at the client-credentials grant, or with the `audience` of a
[token exchange](/provider/token-exchange/); the value must be on the client's
`allowed_exchange_audiences`, otherwise `invalid_target`. Without one, `aud` is the realm issuer URL
as the default resource indicator (§3): the token is for the realm itself — userinfo and the
first-party API — and nothing else. The `auth:oidc` guard accepts a token only when its `aud` names
the issuer or a resource registered under `oidc.resources` (§4) — see
[Resource servers](/advanced/resource-servers/). ID tokens keep `aud` = client id (OIDC Core §2).

`iss` is the realm's issuer URL, and the package checks it wherever it accepts one of its own
tokens — the `auth:oidc` guard, introspection, revocation and token exchange (RFC 9068 §4). A token
signed with the realm's key but issued under another `iss` is rejected.

The token response that carries the access token names the granted scopes in its `scope` member
whenever there are any — see [Endpoints](/provider/endpoints/#the-token-endpoint).
