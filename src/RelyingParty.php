<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient;

use Bambamboole\LaravelOidcClient\Discovery\OidcDiscovery;
use Bambamboole\LaravelOidcClient\Exceptions\OidcClientException;
use Bambamboole\LaravelOidcClient\Token\IdTokenValidator;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RelyingParty
{
    public function __construct(
        private readonly OidcDiscovery $discovery,
        private readonly Http $http,
        private readonly IdTokenValidator $validator,
        private readonly OidcClientManager $manager,
    ) {}

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

    public function handleCallback(Request $request): RedirectResponse
    {
        $state = session()->pull('oidc-client.state');
        $nonce = session()->pull('oidc-client.nonce');
        $verifier = session()->pull('oidc-client.code_verifier');

        if (! is_string($state) || $state === '' || ! is_string($nonce) || $nonce === '' || ! is_string($verifier) || $verifier === '') {
            throw new OidcClientException('The OIDC callback session context is missing or has already been used.');
        }

        if ($request->query('error') !== null) {
            throw new OidcClientException('The provider returned an authorization error.');
        }

        if (! is_string($request->query('state')) || ! hash_equals($state, $request->query('state'))) {
            throw new OidcClientException('The OIDC state parameter did not match.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            throw new OidcClientException('The provider did not return an authorization code.');
        }

        $metadata = $this->discovery->metadata();

        $clientSecret = config('oidc-client.client_secret');

        $response = $this->http->asForm()->post($metadata->tokenEndpoint, array_filter([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => (string) config('oidc-client.redirect_uri'),
            'client_id' => (string) config('oidc-client.client_id'),
            'client_secret' => is_string($clientSecret) && $clientSecret !== '' ? $clientSecret : null,
            'code_verifier' => $verifier,
        ], fn (mixed $value): bool => $value !== null));

        if ($response->failed()) {
            throw new OidcClientException('The token endpoint rejected the code exchange.');
        }

        $idToken = $response->json('id_token');

        if (! is_string($idToken)) {
            throw new OidcClientException('The token response did not include an id_token.');
        }

        $claims = $this->validator->validate($idToken, $nonce);

        $user = $this->manager->resolveUser((string) $claims['sub'], $claims);

        if ($user === null) {
            throw new OidcClientException('No local user matched the id_token subject.');
        }

        Auth::guard((string) config('oidc-client.login_guard', 'web'))->login($user);

        session()->put('oidc-client.tokens', [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'id_token' => $idToken,
        ]);

        $request->session()->regenerate();

        if (config('oidc-client.backchannel_logout.enabled', false)) {
            $sid = $claims['sid'] ?? null;

            if (is_string($sid) && $sid !== '') {
                $request->session()->put('oidc-client.sid', $sid);
                Cache::put(
                    "oidc-client:bclo:session:{$sid}",
                    $request->session()->getId(),
                    now()->addMinutes((int) config('oidc-client.backchannel_logout.retention_minutes', 120)),
                );
            }
        }

        return redirect()->intended((string) config('oidc-client.redirect_after_login', '/dashboard'));
    }
}
