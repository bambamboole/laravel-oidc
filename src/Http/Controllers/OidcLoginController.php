<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Controllers;

use Bambamboole\LaravelOidcClient\RelyingParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class OidcLoginController
{
    public function __invoke(RelyingParty $relyingParty): RedirectResponse
    {
        $guard = (string) config('oidc-client.login_guard', 'web');

        if (Auth::guard($guard)->check()) {
            return redirect()->intended((string) config('oidc-client.redirect_after_login', '/dashboard'));
        }

        return $relyingParty->redirect();
    }
}
