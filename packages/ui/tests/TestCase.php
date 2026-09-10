<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Tests;

use Bambamboole\LaravelOidc\Server\OidcServiceProvider;
use Bambamboole\LaravelOidc\Ui\UiServiceProvider;
use Illuminate\Support\Facades\Http;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\PasskeysServiceProvider;
use Lattice\LatticeServiceProvider;
use Lattice\Support\Testing\InteractsWithLatticeComponents;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Workbench\App\Models\User;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithLatticeComponents;
    use WithLaravelMigrations;
    use WithWorkbench;

    protected $enablesPackageDiscoveries = false;

    protected function getPackageProviders($app): array
    {
        return [
            PasskeysServiceProvider::class,
            LatticeServiceProvider::class,
            OidcServiceProvider::class,
            UiServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('session.driver', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config(['oidc.keys.path' => __DIR__.'/fixtures']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/workbench/database/migrations');
        $this->loadMigrationsFrom(Passkeys::migrationPath());
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/server/database/migrations');
    }
}
