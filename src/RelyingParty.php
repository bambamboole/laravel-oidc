<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class RelyingParty
{
    public function __construct(private readonly OidcDiscovery $discovery) {}

    public function redirect(): RedirectResponse
    {
        $metadata = $this->discovery->metadata();

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        session()->put('oidc-client.state', $state);
        session()->put('oidc-client.nonce', $nonce);
        session()->put('oidc-client.code_verifier', $verifier);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('oidc-client.client_id'),
            'redirect_uri' => (string) config('oidc-client.redirect_uri'),
            'scope' => implode(' ', (array) config('oidc-client.scopes', ['openid'])),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($metadata->authorizationEndpoint.'?'.$query);
    }
}
