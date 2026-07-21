<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests;

use Bambamboole\LaravelOidcClient\Tests\Support\DisabledRoutesTestCase;
use Illuminate\Support\Facades\Route;

class RouteRegistrationTest extends DisabledRoutesTestCase
{
    public function test_it_does_not_register_the_oidc_routes_when_disabled(): void
    {
        $this->assertFalse(Route::has('login'));
        $this->assertFalse(Route::has('login.callback'));
        $this->assertFalse(Route::has('logout'));
    }
}
