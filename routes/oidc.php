<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Controllers\DiscoveryController;
use Bambamboole\LaravelOidc\Http\Controllers\EndSessionController;
use Bambamboole\LaravelOidc\Http\Controllers\IntrospectionController;
use Bambamboole\LaravelOidc\Http\Controllers\JwksController;
use Bambamboole\LaravelOidc\Http\Controllers\RevocationController;
use Bambamboole\LaravelOidc\Http\Controllers\UserinfoController;
use Illuminate\Support\Facades\Route;

$oauth = config('passport.path', 'oauth');

Route::get('/.well-known/jwks.json', JwksController::class)->name('oidc.jwks');
Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');

if (config('oidc.endpoints.userinfo')) {
    Route::match(['get', 'post'], "/{$oauth}/userinfo", UserinfoController::class)
        ->name('oidc.userinfo');
}

if (config('oidc.endpoints.end_session')) {
    Route::match(['get', 'post'], "/{$oauth}/logout", EndSessionController::class)
        ->middleware('web')
        ->name('oidc.logout');
}

if (config('oidc.endpoints.introspection')) {
    Route::post("/{$oauth}/introspect", IntrospectionController::class)
        ->middleware('throttle')
        ->name('oidc.introspect');
}

if (config('oidc.endpoints.revocation')) {
    Route::post("/{$oauth}/revoke", RevocationController::class)
        ->middleware('throttle')
        ->name('oidc.revoke');
}
