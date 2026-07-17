<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Support\EnvironmentFile;
use Bambamboole\LaravelOidc\Support\EnvironmentWriteException;

function envFixture(string $contents): string
{
    $dir = sys_get_temp_dir().'/laravel-oidc-envfile-'.uniqid();
    mkdir($dir);
    $path = $dir.'/.env';
    file_put_contents($path, $contents);

    return $path;
}

it('upserts an existing key and appends a new one in a single write', function () {
    $path = envFixture("APP_NAME=Testing\nOIDC_FIRST_PARTY_CLIENT=stale\n");

    (new EnvironmentFile($path))->write([
        'OIDC_FIRST_PARTY_CLIENT' => 'abc-123',
        'OIDC_FIRST_PARTY_TRUSTED' => 'true',
    ]);

    $contents = (string) file_get_contents($path);

    expect(substr_count($contents, 'OIDC_FIRST_PARTY_CLIENT='))->toBe(1)
        ->and($contents)->toContain('OIDC_FIRST_PARTY_CLIENT=abc-123')
        ->and($contents)->toContain('OIDC_FIRST_PARTY_TRUSTED=true')
        ->and($contents)->toContain('APP_NAME=Testing');
});

it('applies the encoder to every written value', function () {
    $path = envFixture("APP_NAME=Testing\n");

    (new EnvironmentFile($path))->write(
        ['OIDC_PRIVATE_KEY' => "line1\nline2"],
        fn (string $value): string => '"'.str_replace("\n", '\n', $value).'"',
    );

    expect((string) file_get_contents($path))->toContain('OIDC_PRIVATE_KEY="line1\nline2"');
});

it('replaces the target atomically without leaving a temp file behind', function () {
    $path = envFixture("APP_NAME=Testing\n");

    (new EnvironmentFile($path))->write(['OIDC_FIRST_PARTY_CLIENT' => 'abc-123']);

    $siblings = glob(dirname($path).'/*.tmp') ?: [];

    expect($siblings)->toBe([])
        ->and((string) file_get_contents($path))->toContain('OIDC_FIRST_PARTY_CLIENT=abc-123');
});

it('throws when the environment file cannot be read', function () {
    (new EnvironmentFile('/nonexistent/dir/.env'))->write(['A' => 'b']);
})->throws(EnvironmentWriteException::class);
