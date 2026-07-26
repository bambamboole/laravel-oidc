<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Client\Http\Middleware;

use Bambamboole\LaravelOidc\Client\BackchannelLogoutStore;
use Bambamboole\LaravelOidc\Client\OidcClientManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceBackchannelLogout
{
    public function __construct(
        private readonly BackchannelLogoutStore $store,
        private readonly OidcClientManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('oidc-client.backchannel_logout.enabled', false) || ! $request->hasSession()) {
            return $next($request);
        }

        $sid = $request->session()->get('oidc-client.sid');

        if (is_string($sid) && $sid !== '' && $this->store->isRevoked($sid)) {
            $this->manager->terminateLocalSession($request);
        }

        return $next($request);
    }
}
