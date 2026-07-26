<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Http\Controllers;

use Bambamboole\LaravelOidc\Client\BackchannelLogoutStore;
use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Bambamboole\LaravelOidc\Client\Token\LogoutTokenValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class BackchannelLogoutController
{
    public function __invoke(Request $request, LogoutTokenValidator $validator, BackchannelLogoutStore $store): Response
    {
        try {
            ['sid' => $sid, 'jti' => $jti, 'exp' => $exp] = $validator->validate((string) $request->input('logout_token'));

            if ($jti !== null && ! $store->rememberJti($jti, $exp)) {
                throw new OidcClientException("The logout token jti [{$jti}] has already been consumed.");
            }
        } catch (OidcClientException $e) {
            report($e);

            return response()->json(['error' => 'invalid_request'], 400)->header('Cache-Control', 'no-store, private');
        }

        // Immediate teardown for server-side stores (no-op for the cookie driver).
        $sessionId = $store->pullSessionId($sid);
        if ($sessionId !== null) {
            Session::getHandler()->destroy($sessionId);
        }

        // Universal fallback marker for the enforcement middleware.
        $store->markRevoked($sid);

        return response('', 200)->header('Cache-Control', 'no-store, private');
    }
}
