<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Controllers;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

        try {
            $endSession = $discovery->metadata()->endSessionEndpoint;
        } catch (ConnectionException|RequestException|OidcClientException) {
            return redirect('/');
        }

        if ($endSession === null) {
            return redirect('/');
        }

        $postLogoutRedirectUri = config('oidc-client.post_logout_redirect_uri');

        $query = http_build_query(array_filter([
            'id_token_hint' => is_string($idToken) ? $idToken : null,
            'post_logout_redirect_uri' => is_string($postLogoutRedirectUri) && $postLogoutRedirectUri !== '' ? $postLogoutRedirectUri : null,
        ]));

        $separator = str_contains($endSession, '?') ? '&' : '?';

        return redirect()->away($endSession.$separator.$query);
    }
}
