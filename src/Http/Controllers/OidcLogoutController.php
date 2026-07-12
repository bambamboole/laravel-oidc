<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Controllers;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OidcLogoutController
{
    public function __invoke(Request $request, OidcDiscovery $discovery): RedirectResponse
    {
        $idToken = $request->session()->get('oidc-client.tokens.id_token');

        Auth::guard((string) config('oidc-client.login_guard', 'web'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $endSession = $discovery->metadata()->endSessionEndpoint;

        if ($endSession === null) {
            return redirect('/');
        }

        $query = http_build_query(array_filter([
            'id_token_hint' => is_string($idToken) ? $idToken : null,
            'post_logout_redirect_uri' => (string) config('oidc-client.redirect_after_login', '/dashboard'),
        ]));

        return redirect()->away($endSession.'?'.$query);
    }
}
