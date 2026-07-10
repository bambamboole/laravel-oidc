<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Http\Controllers\JwksController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/jwks.json', JwksController::class)->name('oidc.jwks');
