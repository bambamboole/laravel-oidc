<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Routing\HandlerRegistrar;
use Bambamboole\LaravelOidc\Server\Tests\TestCase;
use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Route;

/**
 * The committed OpenAPI document must match what Scramble generates from the
 * current routes, requests and resources. Regenerate it with
 * `composer openapi` (sets OPENAPI_WRITE=1) after changing the API.
 */
it('keeps the committed OpenAPI document in sync with the routes', function () {
    config(['oidc.admin.enabled' => true]);
    app(HandlerRegistrar::class)->register();
    Route::getRoutes()->refreshNameLookups();

    $document = app(Generator::class)();
    $generated = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    $path = TestCase::openApiSpecPath();

    if (getenv('OPENAPI_WRITE') === '1') {
        file_put_contents($path, $generated);
    }

    expect(file_exists($path))->toBeTrue('Run `composer openapi` to generate the document.')
        ->and(file_get_contents($path))->toBe($generated);

    $paths = array_keys($document['paths'] ?? []);

    expect($paths)->toBe([
        '/oauth/admin/clients',
        '/oauth/admin/clients/{client}',
        '/oauth/admin/clients/{client}/secret',
    ]);
});
