<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Http\Controllers\OidcLoginController;
use Illuminate\Support\Facades\Route;

/** @var array<string, array{path: string, name: string}> $routes */
$routes = (array) config('oidc-client.routes');

Route::middleware('web')->group(function () use ($routes): void {
    Route::get($routes['login']['path'], OidcLoginController::class)->name($routes['login']['name']);
});
