<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\OAuthenticatable;

class UserinfoController
{
    public function __invoke(Request $request, ClaimsResolver $claims): JsonResponse
    {
        $user = $request->user('api');

        if (! $user instanceof OAuthenticatable) {
            abort(401);
        }

        $token = $user->currentAccessToken();
        $scopes = $token instanceof AccessToken ? $token->oauth_scopes : [];

        return response()->json(array_merge(
            ['sub' => (string) $user->getAuthIdentifier()],
            $claims->resolve($user)->forScopes($scopes),
        ));
    }
}
