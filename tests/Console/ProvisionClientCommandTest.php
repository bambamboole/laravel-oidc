<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;

function clientCommandEnv(string $contents = "APP_NAME=Testing\n"): string
{
    $directory = sys_get_temp_dir().'/laravel-oidc-client-command-'.uniqid();
    File::makeDirectory($directory, 0755, true, true);
    File::put($directory.'/.env', $contents);
    app()->useEnvironmentPath($directory);

    return $directory.'/.env';
}

it('creates and prints first-party credentials without changing env', function () {
    $env = clientCommandEnv();
    $before = File::get($env);

    $this->artisan('oidc:client', [
        '--first-party' => true,
        '--name' => 'First-party app',
        '--redirect-uri' => ['https://app.test/login/callback'],
        '--post-logout-redirect-uri' => ['https://app.test'],
        '--audience' => ['https://api.test/orders'],
        '--no-interaction' => true,
    ])->expectsOutputToContain('OIDC_FIRST_PARTY_CLIENT=')
        ->expectsOutputToContain('OIDC_RP_CLIENT_SECRET=')
        ->assertSuccessful();

    expect(File::get($env))->toBe($before);
});

it('reconciles without printing a secret and writes selected config explicitly', function () {
    $env = clientCommandEnv("APP_NAME=Testing\nOTHER=keep\n");
    $arguments = [
        '--first-party' => true,
        '--name' => 'First-party app',
        '--redirect-uri' => ['https://app.test/login/callback'],
        '--trusted' => true,
        '--write-env' => true,
        '--no-interaction' => true,
    ];

    $this->artisan('oidc:client', $arguments)->assertSuccessful();
    $this->artisan('oidc:client', $arguments)
        ->doesntExpectOutputToContain('OIDC_RP_CLIENT_SECRET=')
        ->assertSuccessful();

    expect(File::get($env))->toContain('OIDC_FIRST_PARTY_CLIENT=')
        ->toContain('OIDC_FIRST_PARTY_TRUSTED=true')
        ->toContain('OTHER=keep');
});

it('prompts for required interactive values and confirms env writes', function () {
    $env = clientCommandEnv();

    $this->artisan('oidc:client', [
        '--first-party' => true,
        '--write-env' => true,
    ])->expectsQuestion('Client name', 'First-party app')
        ->expectsQuestion('Redirect URIs (comma separated)', 'https://app.test/login/callback')
        ->expectsConfirmation('Write OIDC_FIRST_PARTY_CLIENT and OIDC_FIRST_PARTY_TRUSTED to .env?', 'yes')
        ->assertSuccessful();

    expect(File::get($env))->toContain('OIDC_FIRST_PARTY_CLIENT=');
});

it('adopts an eligible client without printing its stored hash', function () {
    $env = clientCommandEnv();
    $client = app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient('Legacy', ['https://legacy.test/callback']);
    $hash = (string) $client->getRawOriginal('secret');

    $this->artisan('oidc:client', [
        '--first-party' => true,
        '--name' => 'First-party app',
        '--redirect-uri' => ['https://app.test/login/callback'],
        '--adopt' => (string) $client->getKey(),
        '--no-interaction' => true,
    ])->doesntExpectOutputToContain($hash)
        ->doesntExpectOutputToContain('OIDC_RP_CLIENT_SECRET=')
        ->assertSuccessful();

    expect(File::get($env))->toBe("APP_NAME=Testing\n")
        ->and($client->refresh()->getRawOriginal('oidc_provisioning_key'))->toBe('first-party');
});

it('leaves env unchanged after a provisioning failure', function () {
    $env = clientCommandEnv();
    $before = File::get($env);

    $this->artisan('oidc:client', [
        '--first-party' => true,
        '--name' => 'First-party app',
        '--redirect-uri' => ['not-a-uri'],
        '--write-env' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect(File::get($env))->toBe($before);
});

it('requires explicit rotation before printing a replacement secret', function () {
    $base = [
        '--first-party' => true,
        '--name' => 'First-party app',
        '--redirect-uri' => ['https://app.test/login/callback'],
        '--no-interaction' => true,
    ];

    $this->artisan('oidc:client', $base)->assertSuccessful();
    $this->artisan('oidc:client', $base)
        ->doesntExpectOutputToContain('OIDC_RP_CLIENT_SECRET=')
        ->assertSuccessful();
    $this->artisan('oidc:client', [...$base, '--rotate' => true])
        ->expectsOutputToContain('OIDC_RP_CLIENT_SECRET=')
        ->assertSuccessful();
});

it('rejects calls without first-party mode', function () {
    $this->artisan('oidc:client', [
        '--name' => 'First-party app',
        '--redirect-uri' => ['https://app.test/login/callback'],
        '--no-interaction' => true,
    ])->assertExitCode(2);
});

it('rejects missing required values when running non-interactively', function (array $arguments) {
    $this->artisan('oidc:client', [
        '--first-party' => true,
        '--no-interaction' => true,
        ...$arguments,
    ])->assertExitCode(2);
})->with([
    'name' => [['--redirect-uri' => ['https://app.test/login/callback']]],
    'redirect URI' => [['--name' => 'First-party app']],
]);

it('does not write env when interactive confirmation is declined', function () {
    $env = clientCommandEnv();
    $before = File::get($env);

    $this->artisan('oidc:client', [
        '--first-party' => true,
        '--name' => 'First-party app',
        '--redirect-uri' => ['https://app.test/login/callback'],
        '--write-env' => true,
    ])->expectsConfirmation('Write OIDC_FIRST_PARTY_CLIENT and OIDC_FIRST_PARTY_TRUSTED to .env?', 'no')
        ->expectsOutput('Credentials were not written to .env.')
        ->assertSuccessful();

    expect(File::get($env))->toBe($before);
});
