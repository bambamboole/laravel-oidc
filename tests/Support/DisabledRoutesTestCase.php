<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests\Support;

use Bambamboole\LaravelOidcClient\Tests\TestCase;

abstract class DisabledRoutesTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('oidc-client.enabled', false);
    }
}
