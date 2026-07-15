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

The legacy `scopes` array claim (`["openid", "email"]`) is retained alongside `scope`. It is kept
because Passport's `auth:api` guard and this package's userinfo endpoint read it — dropping it
would break authentication. Both claims describe the same grant; `scope` is the RFC 9068 form and
`scopes` is the compatibility form.

`aud` defaults to the requesting client's id. It is overridden by
[token exchange](/provider/token-exchange/), which sets `aud` to the requested
`resource`/`audience` instead so the token targets a downstream resource server.
