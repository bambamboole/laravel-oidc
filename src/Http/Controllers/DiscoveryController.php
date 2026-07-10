<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Contracts\ScopeRepository;
use Bambamboole\LaravelOidc\Issuer;
use Bambamboole\LaravelOidc\Scopes\Scope;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Passport;

class DiscoveryController
{
    public function __invoke(ScopeRepository $scopes): JsonResponse
    {
        $grantTypes = ['authorization_code', 'refresh_token'];

        if (Passport::$deviceCodeGrantEnabled) {
            $grantTypes[] = 'urn:ietf:params:oauth:grant-type:device_code';
        }

        $document = [
            'issuer' => Issuer::url(),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'jwks_uri' => route('oidc.jwks'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => $grantTypes,
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => $scopes->all()
                ->reject(fn (Scope $scope) => $scope->hidden)
                ->map(fn (Scope $scope) => $scope->id)
                ->values()
                ->all(),
            'claims_supported' => config('oidc.claims_supported'),
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        ];

        if (config('oidc.endpoints.userinfo')) {
            $document['userinfo_endpoint'] = route('oidc.userinfo');
        }

        if (config('oidc.endpoints.end_session')) {
            $document['end_session_endpoint'] = route('oidc.logout');
        }

        if (config('oidc.endpoints.introspection')) {
            $document['introspection_endpoint'] = route('oidc.introspect');
        }

        if (config('oidc.endpoints.revocation')) {
            $document['revocation_endpoint'] = route('oidc.revoke');
        }

        return response()->json($document)->header('Cache-Control', 'max-age=3600, public');
    }
}
