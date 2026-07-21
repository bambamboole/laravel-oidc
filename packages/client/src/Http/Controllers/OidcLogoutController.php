<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Controllers;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Bambamboole\LaravelOidcClient\OidcClientManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OidcLogoutController
{
    public function __invoke(Request $request, OidcDiscovery $discovery, OidcClientManager $manager): RedirectResponse
    {
        $idToken = $request->session()->get('oidc-client.tokens.id_token');

        $manager->terminateLocalSession($request);

        try {
            $endSession = $discovery->metadata()->endSessionEndpoint;
        } catch (ConnectionException|RequestException|OidcClientException) {
            $endSession = null;
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
