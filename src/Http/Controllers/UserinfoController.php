<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Contracts\ClaimsResolver;
use Bambamboole\LaravelOidc\Hooks\Artifact;
use Bambamboole\LaravelOidc\Hooks\ClaimHooks;
use Bambamboole\LaravelOidc\Hooks\ClaimsBag;
use Bambamboole\LaravelOidc\Hooks\Context\UserinfoContext;
use Bambamboole\LaravelOidc\Hooks\Trigger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\OAuthenticatable;

class UserinfoController
{
    public function __invoke(Request $request, ClaimsResolver $claims, ClaimHooks $hooks): JsonResponse
    {
        $user = $request->user('api');

        if (! $user instanceof OAuthenticatable) {
            abort(401);
        }

        $token = $user->currentAccessToken();
        $scopes = $token instanceof AccessToken ? $token->oauth_scopes : [];

        $bag = new ClaimsBag(Artifact::Userinfo);
        $hooks->run(Trigger::Userinfo, new UserinfoContext($user, null, $scopes, $bag));

        return response()->json(array_merge(
            ['sub' => (string) $user->getAuthIdentifier()],
            $claims->resolve($user)->forScopes($scopes),
            $bag->all(),
        ));
    }
}
