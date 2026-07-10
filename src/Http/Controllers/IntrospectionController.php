<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Http\ClientCredentials;
use Bambamboole\LaravelOidc\Token\TokenInspector;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class IntrospectionController
{
    public function __invoke(Request $request, ClientCredentials $credentials, TokenInspector $inspector): JsonResponse
    {
        $clientId = $credentials->validate($request);

        abort_if($clientId === null, 401, 'invalid_client');

        $tokenValue = (string) $request->input('token');

        if ($request->input('token_type_hint') === 'refresh_token') {
            return $this->introspectRefreshToken($tokenValue, $clientId, $inspector);
        }

        $token = $inspector->accessToken($tokenValue);

        if (! $token instanceof Token) {
            return response()->json(['active' => false]);
        }

        $expiresAt = $token->getAttribute('expires_at');
        $tokenClientId = (string) $token->getAttribute('client_id');

        if ((bool) $token->getAttribute('revoked')
            || ($expiresAt instanceof CarbonInterface && $expiresAt->isPast())
            || $tokenClientId !== $clientId) {
            return response()->json(['active' => false]);
        }

        $scopes = $token->getAttribute('scopes');

        return response()->json([
            'active' => true,
            'token_type' => 'Bearer',
            'scope' => implode(' ', is_array($scopes) ? $scopes : []),
            'client_id' => $tokenClientId,
            'sub' => (string) $token->getAttribute('user_id'),
            'exp' => $expiresAt instanceof CarbonInterface ? $expiresAt->getTimestamp() : null,
        ]);
    }

    private function introspectRefreshToken(string $tokenValue, string $clientId, TokenInspector $inspector): JsonResponse
    {
        $payload = $inspector->refreshTokenPayload($tokenValue);

        if ($payload === null || (string) ($payload->client_id ?? '') !== $clientId) {
            return response()->json(['active' => false]);
        }

        $refreshTokenId = $payload->refresh_token_id ?? null;
        $refreshToken = is_string($refreshTokenId) ? Passport::refreshToken()->newQuery()->find($refreshTokenId) : null;
        $expireTime = $payload->expire_time ?? null;

        if (! $refreshToken instanceof RefreshToken
            || (bool) $refreshToken->getAttribute('revoked')
            || ! is_int($expireTime)
            || $expireTime < time()) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'client_id' => $clientId,
            'sub' => (string) ($payload->user_id ?? ''),
            'exp' => $expireTime,
        ]);
    }
}
