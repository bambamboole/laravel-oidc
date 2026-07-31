<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Client\Http\Controllers\BackchannelLogoutController;
use Bambamboole\LaravelOidc\Client\Http\Controllers\OidcCallbackController;
use Bambamboole\LaravelOidc\Client\Http\Controllers\OidcLoginController;
use Bambamboole\LaravelOidc\Client\Http\Controllers\OidcLogoutController;
use Illuminate\Support\Facades\Route;

Route::get('login', OidcLoginController::class)->name('login')->middleware('web');
Route::get('login/callback', OidcCallbackController::class)->name('login.callback')->middleware('web');
Route::post('logout', OidcLogoutController::class)->name('logout')->middleware('web');

if (config('oidc-client.backchannel_logout.enabled', false)) {
    Route::post('oidc/backchannel-logout', BackchannelLogoutController::class)
        ->name('oidc.backchannel-logout')
        ->middleware('throttle:60,1');
}
