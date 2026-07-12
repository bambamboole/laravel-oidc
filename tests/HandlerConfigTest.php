<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Http\Controllers\OidcCallbackController;
use Bambamboole\LaravelOidcClient\Http\Controllers\OidcLoginController;
use Bambamboole\LaravelOidcClient\Http\Controllers\OidcLogoutController;
use Bambamboole\LaravelOidcClient\Routing\Handler;
use Bambamboole\LaravelOidcClient\Routing\HandlerConfig;

it('resolves each handler from the default config', function () {
    $login = Handler::Login->config();
    $callback = Handler::Callback->config();
    $logout = Handler::Logout->config();

    if (! $login instanceof HandlerConfig || ! $callback instanceof HandlerConfig || ! $logout instanceof HandlerConfig) {
        throw new RuntimeException('The default handlers should all be configured.');
    }

    expect($login->route)->toBe('login')
        ->and($login->controller)->toBe(OidcLoginController::class)
        ->and($login->middleware)->toBe(['web'])
        ->and($callback->route)->toBe('login/callback')
        ->and($callback->controller)->toBe(OidcCallbackController::class)
        ->and($logout->route)->toBe('logout')
        ->and($logout->controller)->toBe(OidcLogoutController::class);
});

it('exposes the intrinsic http verb per handler', function () {
    expect(Handler::Login->method())->toBe('get')
        ->and(Handler::Callback->method())->toBe('get')
        ->and(Handler::Logout->method())->toBe('post');
});

it('reflects a custom route path, controller, and middleware from config', function () {
    config()->set('oidc-client.handlers.'.Handler::Login->value, [
        'route' => 'sign-in',
        'controller' => OidcLogoutController::class,
        'middleware' => ['web', 'guest'],
    ]);

    $config = Handler::Login->config();

    if (! $config instanceof HandlerConfig) {
        throw new RuntimeException('The overridden login handler should be configured.');
    }

    expect($config->route)->toBe('sign-in')
        ->and($config->controller)->toBe(OidcLogoutController::class)
        ->and($config->middleware)->toBe(['web', 'guest']);
});

it('treats a disabled handler as false', function () {
    config()->set('oidc-client.handlers.'.Handler::Logout->value, false);

    expect(Handler::Logout->config())->toBeFalse();
});

it('treats an absent handler as false', function () {
    config()->set('oidc-client.handlers', []);

    expect(Handler::Login->config())->toBeFalse();
});
