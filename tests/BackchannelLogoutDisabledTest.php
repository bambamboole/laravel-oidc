<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests;

use Bambamboole\LaravelOidcClient\Http\Middleware\EnforceBackchannelLogout;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

class BackchannelLogoutDisabledTest extends TestCase
{
    public function test_it_does_not_register_the_backchannel_logout_route_when_disabled(): void
    {
        $this->assertFalse(Route::has('oidc.backchannel-logout'));
    }

    public function test_it_does_not_append_the_enforcement_middleware_to_the_web_group_when_disabled(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $this->assertNotContains(
            EnforceBackchannelLogout::class,
            $router->getMiddlewareGroups()['web'] ?? [],
        );
    }
}
