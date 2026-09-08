<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Http\Middleware;

use Bambamboole\LaravelOidc\Server\Http\OAuthError;
use Bambamboole\LaravelOidc\Server\Issuer;
use Bambamboole\LaravelOidc\Server\Scopes\AdminScope;
use Bambamboole\LaravelOidc\Server\Token\TokenInspector;
use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Lcobucci\JWT\Token\Plain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the client administration API. Only a client_credentials
 * access token qualifies: it must verify as this issuer's `at+jwt`, be
 * unexpired and unrevoked, carry no user, be addressed to this issuer, hold
 * the admin scope explicitly (never `*`), and belong to a client that still
 * lists that scope. `auth:oidc` cannot serve here because it requires a user.
 */
final class AuthenticateAdminClient
{
    public const string ClientIdAttribute = 'oidc_admin_client_id';

    public function __construct(private readonly TokenInspector $inspector) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $jwt = $request->bearerToken();

        if ($jwt === null) {
            OAuthError::bearer('invalid_token', 401, withRealm: true);
        }

        $parsed = $this->inspector->parse($jwt);

        if ($parsed === null || $parsed->headers()->get('typ') !== 'at+jwt' || $this->isExpired($parsed)) {
            OAuthError::bearer('invalid_token', 401, withRealm: true);
        }

        $token = $this->inspector->tokenForParsed($parsed);

        if (! $token instanceof Token || $token->getAttribute('revoked') || $token->getAttribute('user_id') !== null) {
            OAuthError::bearer('invalid_token', 401, withRealm: true);
        }

        $clientId = (string) $token->getAttribute('client_id');

        if (! $this->isAddressedToIssuer($parsed, $clientId)) {
            OAuthError::bearer('invalid_token', 401, withRealm: true);
        }

        $client = Passport::client()->newQuery()->whereKey($clientId)->where('revoked', false)->whereNull('owner_id')->first();

        if (! in_array(AdminScope::id(), (array) $token->getAttribute('scopes'), true)
            || $client === null
            || ! $client->confidential()
            || ! AdminScope::isListedOn($client)) {
            OAuthError::bearer('insufficient_scope', 403, withRealm: true);
        }

        $request->attributes->set(self::ClientIdAttribute, $clientId);

        return $next($request);
    }

    private function isExpired(Plain $parsed): bool
    {
        $exp = $parsed->claims()->get('exp');
        $expiry = $exp instanceof DateTimeInterface ? $exp->getTimestamp() : (is_numeric($exp) ? (int) $exp : 0);

        return $expiry <= time();
    }

    private function isAddressedToIssuer(Plain $parsed, string $clientId): bool
    {
        $audience = $parsed->claims()->get('aud');
        $audience = array_values(array_filter(is_array($audience) ? $audience : [$audience], 'is_string'));

        return in_array(Issuer::url(), $audience, true) || in_array($clientId, $audience, true);
    }
}
