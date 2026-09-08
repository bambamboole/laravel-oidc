<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Tests;

use Bambamboole\LaravelOidc\Server\Http\Middleware\AuthenticateAdminClient;
use Bambamboole\LaravelOidc\Server\Scopes\AdminScope;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Passkeys\Passkeys;
use Laravel\Passport\Passport;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Workbench\App\Models\User;

abstract class TestCase extends BaseTestCase
{
    use WithLaravelMigrations;
    use WithWorkbench;

    public const string TOKEN_EXCHANGE_GRANT = 'urn:ietf:params:oauth:grant-type:token-exchange';

    protected function getEnvironmentSetUp($app): void
    {
        $token = ParallelTesting::token();
        $workspace = sys_get_temp_dir().'/laravel-oidc-package-tests';
        $database = $token
            ? $workspace.'/test_'.$token.'.sqlite'
            : $workspace.'/database-'.getmypid().'.sqlite';

        File::makeDirectory(dirname($database), 0755, true, true);

        if (! file_exists($database)) {
            touch($database);
        }

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', $database);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('auth.guards.api', ['driver' => 'passport', 'provider' => 'users']);
        $app['config']->set('session.driver', 'array');

        $this->configureOpenApiGeneration($app);
    }

    /**
     * Scramble and Spectacular only run in this harness: they generate and
     * validate the client administration OpenAPI document, which ships as
     * resources/openapi/client-administration.json.
     *
     * @param  Application  $app
     */
    private function configureOpenApiGeneration($app): void
    {
        $version = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/.release-please-manifest.json'), true)['.'] ?? '0.0.0';

        $app['config']->set('scramble.api_path', 'oauth/admin/*');
        $app['config']->set('scramble.servers', ['Identity provider' => 'https://id.example.com']);
        $app['config']->set('scramble.info.version', $version);
        $app['config']->set('scramble.ui.title', 'laravel-oidc client administration API');
        $app['config']->set('spectacular.openapi.info.description', 'Manage OAuth clients of a laravel-oidc identity provider from infrastructure as code.');
        $app['config']->set('spectacular.openapi.validation.path', self::openApiSpecPath());
        $app['config']->set('spectacular.openapi.security.middleware', [AuthenticateAdminClient::class]);
        $app['config']->set('spectacular.openapi.security.schemes', [
            'adminClient' => [
                'type' => 'oauth2',
                'description' => 'A client_credentials access token of a confidential client whose scopes list the admin scope.',
                'flows' => [
                    'clientCredentials' => [
                        'token_url' => '/oauth/token',
                        'scopes' => [AdminScope::DefaultId => 'Administer OAuth clients'],
                    ],
                ],
            ],
        ]);
    }

    public static function openApiSpecPath(): string
    {
        return dirname(__DIR__).'/resources/openapi/client-administration.json';
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Passport::$validateKeyPermissions = false;
        Passport::loadKeysFrom(__DIR__.'/fixtures');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/vendor/laravel/passport/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__).'/workbench/database/migrations');
        $this->loadMigrationsFrom(Passkeys::migrationPath());
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }
}
