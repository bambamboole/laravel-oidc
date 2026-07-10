<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Controllers\DiscoveryController;
use Bambamboole\LaravelOidc\Http\Controllers\JwksController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/jwks.json', JwksController::class)->name('oidc.jwks');
Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');

Route::match(['get', 'post'], '/oauth/userinfo', fn () => abort(501))->name('oidc.userinfo');
Route::match(['get', 'post'], '/oauth/logout', fn () => abort(501))->name('oidc.logout');
Route::post('/oauth/introspect', fn () => abort(501))->name('oidc.introspect');
Route::post('/oauth/revoke', fn () => abort(501))->name('oidc.revoke');
