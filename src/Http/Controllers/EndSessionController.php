<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Controllers;

use Bambamboole\LaravelOidc\Issuer;
use Bambamboole\LaravelOidc\Token\PassportKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Throwable;

class EndSessionController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $redirectUri = $this->validatedPostLogoutUri($request);

        Auth::guard(config('passport.guard', null))->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($redirectUri === null) {
            return redirect(config('oidc.logout_redirect', '/'));
        }

        $state = $request->input('state');

        if ($state === null) {
            return redirect()->away($redirectUri);
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return redirect()->away(
            $redirectUri.$separator.http_build_query(['state' => $state]),
        );
    }

    private function validatedPostLogoutUri(Request $request): ?string
    {
        $hint = $request->input('id_token_hint');
        $uri = $request->input('post_logout_redirect_uri');

        if ($hint === null || $uri === null) {
            return null;
        }

        try {
            $token = (new Parser(new JoseEncoder))->parse($hint);
        } catch (Throwable) {
            return null;
        }

        $valid = $token instanceof Plain && (new Validator)->validate(
            $token,
            new SignedWith(new Sha256, InMemory::plainText(PassportKeys::publicKey())),
            new IssuedBy(Issuer::url()),
        );

        if (! $valid) {
            return null;
        }

        $clientId = $token->claims()->get('aud')[0] ?? null;
        $client = $clientId !== null ? Passport::client()::query()->find($clientId) : null;

        if ($client === null) {
            return null;
        }

        $registered = json_decode((string) $client->getRawOriginal('post_logout_redirect_uris'), true) ?? [];

        return in_array($uri, $registered, true) ? $uri : null;
    }
}
