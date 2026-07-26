<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Http\Controllers;

use Bambamboole\LaravelOidc\Client\Exceptions\OidcClientException;
use Bambamboole\LaravelOidc\Client\RelyingParty;
use Bambamboole\LaravelOidc\Client\Routing\Handler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OidcCallbackController
{
    public function __invoke(Request $request, RelyingParty $relyingParty): RedirectResponse
    {
        try {
            return $relyingParty->handleCallback($request);
        } catch (OidcClientException) {
            return redirect()->route(Handler::Login->value)->withErrors([
                'oidc' => 'Sign-in failed. Please try again.',
            ]);
        }
    }
}
