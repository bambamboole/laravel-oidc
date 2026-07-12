<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Controllers\EmailVerificationNotificationController;
use Bambamboole\LaravelOidc\Auth\Controllers\EmailVerificationPromptController;
use Bambamboole\LaravelOidc\Auth\Controllers\NewPasswordController;
use Bambamboole\LaravelOidc\Auth\Controllers\PasswordResetLinkController;
use Bambamboole\LaravelOidc\Auth\Controllers\RegisteredUserController;
use Bambamboole\LaravelOidc\Auth\Controllers\VerifyEmailController;
use Illuminate\Support\Facades\Route;

$guard = (string) config('oidc.auth.guard', 'web');

Route::middleware('web')->group(function () use ($guard): void {
    Route::middleware('guest:'.$guard)->group(function (): void {
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
    });

    Route::middleware('auth:'.$guard)->group(function (): void {
        Route::get('email/verify', EmailVerificationPromptController::class)->name('verification.notice');

        Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });
});
