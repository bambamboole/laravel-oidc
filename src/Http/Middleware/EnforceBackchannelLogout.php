<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceBackchannelLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('oidc-client.backchannel_logout.enabled', false) || ! $request->hasSession()) {
            return $next($request);
        }

        $sid = $request->session()->get('oidc-client.sid');

        if (is_string($sid) && $sid !== '' && Cache::has("oidc-client:bclo:revoked:{$sid}")) {
            Auth::guard((string) config('oidc-client.login_guard', 'web'))->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
