<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Controllers;

use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Bambamboole\LaravelOidcClient\RelyingParty;
use Bambamboole\LaravelOidcClient\Routing\Handler;
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
