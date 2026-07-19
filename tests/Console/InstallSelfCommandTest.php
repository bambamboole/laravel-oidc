<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Passport\Passport;

function installSelfEnv(string $contents = "APP_NAME=Testing\n"): string
{
    $directory = sys_get_temp_dir().'/laravel-oidc-install-self-'.uniqid();
    File::makeDirectory($directory, 0755, true, true);
    File::put($directory.'/.env', $contents);
    app()->useEnvironmentPath($directory);

    return $directory.'/.env';
}

it('provisions the first-party client and writes both env halves', function () {
    $env = installSelfEnv();
    config(['oidc-client' => [], 'app.url' => 'https://app.test', 'oidc.issuer' => null]);

    $this->artisan('oidc:install-self', ['--force' => true])->assertSuccessful();

    $client = Passport::client()->newQuery()->where('oidc_provisioning_key', 'first-party')->firstOrFail();
    $clientId = (string) $client->getKey();
    $contents = (string) File::get($env);

    expect($contents)
        ->toContain('OIDC_FIRST_PARTY_CLIENT='.$clientId)
        ->toContain('OIDC_FIRST_PARTY_TRUSTED=true')
        ->toContain('OIDC_RP_ENABLED=true')
        ->toContain('OIDC_RP_ISSUER=https://app.test')
        ->toContain('OIDC_RP_CLIENT_ID='.$clientId)
        ->toContain('OIDC_RP_REDIRECT_URI=https://app.test/login/callback')
        ->toContain('OIDC_RP_POST_LOGOUT_REDIRECT_URI=https://app.test')
        ->toContain('OIDC_ISSUER=https://app.test')
        ->and(preg_match('/^OIDC_RP_CLIENT_SECRET=.+$/m', $contents))->toBe(1);
});

it('fails when the relying-party package is not installed', function () {
    $env = installSelfEnv();
    $before = File::get($env);
    config(['oidc-client' => null, 'app.url' => 'https://app.test']);

    $this->artisan('oidc:install-self', ['--force' => true])->assertFailed();

    expect(File::get($env))->toBe($before);
});
