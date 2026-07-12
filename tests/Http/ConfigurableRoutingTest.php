<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

it('registers the OIDC oauth endpoints under the configured passport.path prefix', function () {
    $uri = Route::getRoutes()->getByName('oidc.userinfo')->uri();
    expect($uri)->toStartWith(config('passport.path', 'oauth').'/');
});

it('authenticates userinfo via the configured oidc.api_guard', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    Passport::actingAs($user, ['openid']);

    expect(config('oidc.api_guard'))->toBe('api');
    $this->getJson('/oauth/userinfo')->assertOk();
});
