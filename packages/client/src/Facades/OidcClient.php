<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Facades;

use Bambamboole\LaravelOidcClient\OidcClientManager;
use Bambamboole\LaravelOidcClient\Testing\OidcClientFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void resolveUsersUsing(\Closure $callback)
 * @method static void routes()
 * @method static \Illuminate\Contracts\Auth\StatefulGuard guard()
 * @method static \Illuminate\Http\RedirectResponse redirectAfterLogin()
 * @method static void terminateLocalSession(\Illuminate\Http\Request $request)
 * @method static \Bambamboole\LaravelOidcClient\Testing\OidcClientFake fake()
 *
 * @see OidcClientManager
 */
class OidcClient extends Facade
{
    public static function fake(): OidcClientFake
    {
        return OidcClientFake::start();
    }

    protected static function getFacadeAccessor(): string
    {
        return OidcClientManager::class;
    }
}
