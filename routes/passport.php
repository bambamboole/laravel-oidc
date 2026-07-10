<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Controllers\ApproveAuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\AuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\DenyAuthorizationController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

Route::group([
    'as' => 'passport.',
    'prefix' => config('passport.path', 'oauth'),
    'namespace' => 'Laravel\Passport\Http\Controllers',
    'middleware' => config('passport.middleware', []),
], function (): void {
    Route::post('/token', [
        'uses' => 'AccessTokenController@issueToken',
        'as' => 'token',
        'middleware' => 'throttle',
    ]);

    Route::get('/authorize', [AuthorizationController::class, 'authorize'])
        ->name('authorizations.authorize')
        ->middleware('web');

    if (Passport::$deviceCodeGrantEnabled) {
        Route::get('/device', [
            'uses' => 'DeviceUserCodeController',
            'as' => 'device',
            'middleware' => 'web',
        ]);

        Route::post('/device/code', [
            'uses' => 'DeviceCodeController',
            'as' => 'device.code',
            'middleware' => 'throttle',
        ]);
    }

    $guard = config('passport.guard', null);

    Route::middleware(['web', $guard ? 'auth:'.$guard : 'auth'])->group(function (): void {
        Route::post('/token/refresh', [
            'uses' => 'TransientTokenController@refresh',
            'as' => 'token.refresh',
        ]);

        Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])
            ->name('authorizations.approve');

        Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])
            ->name('authorizations.deny');

        if (Passport::$deviceCodeGrantEnabled) {
            Route::get('/device/authorize', [
                'uses' => 'DeviceAuthorizationController',
                'as' => 'device.authorizations.authorize',
            ]);

            Route::post('/device/authorize', [
                'uses' => 'ApproveDeviceAuthorizationController',
                'as' => 'device.authorizations.approve',
            ]);

            Route::delete('/device/authorize', [
                'uses' => 'DenyDeviceAuthorizationController',
                'as' => 'device.authorizations.deny',
            ]);
        }
    });
});
