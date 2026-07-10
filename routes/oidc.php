<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Controllers\DiscoveryController;
use Bambamboole\LaravelOidc\Http\Controllers\JwksController;
use Bambamboole\LaravelOidc\Http\Controllers\UserinfoController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\CheckToken;

Route::get('/.well-known/jwks.json', JwksController::class)->name('oidc.jwks');
Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');

if (config('oidc.endpoints.userinfo')) {
    Route::match(['get', 'post'], '/oauth/userinfo', UserinfoController::class)
        ->middleware(['auth:api', CheckToken::using('openid')])
        ->name('oidc.userinfo');
}

if (config('oidc.endpoints.end_session')) {
    Route::match(['get', 'post'], '/oauth/logout', fn () => abort(501))->name('oidc.logout');
}

if (config('oidc.endpoints.introspection')) {
    Route::post('/oauth/introspect', fn () => abort(501))->name('oidc.introspect');
}

if (config('oidc.endpoints.revocation')) {
    Route::post('/oauth/revoke', fn () => abort(501))->name('oidc.revoke');
}
