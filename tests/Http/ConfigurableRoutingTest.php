<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Controllers\AuthenticatedSessionController;
use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Http\Controllers\JwksController;
use Bambamboole\LaravelOidc\Routing\Handler;
use Bambamboole\LaravelOidc\Routing\HandlerConfig;
use Bambamboole\LaravelOidc\Routing\HandlerRegistrar;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

/**
 * Registers the configured handlers into a fresh, isolated router so we can
 * inspect the result without the routes already bound during boot.
 */
function registerHandlersInFreshRouter(): Router
{
    $router = new Router(new Dispatcher, app());
    Route::swap($router);

    app(HandlerRegistrar::class)->register();

    // Names are applied fluently after each route is added, so rebuild the
    // name lookup the same way the request lifecycle does before matching.
    $router->getRoutes()->refreshNameLookups();

    return $router;
}

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

it('registers a route for every enabled handler', function () {
    $routes = registerHandlersInFreshRouter()->getRoutes();

    foreach (Handler::cases() as $handler) {
        expect($routes->getByName($handler->value))->not->toBeNull();
    }
});

it('registers each handler with its intrinsic method', function () {
    $routes = registerHandlersInFreshRouter()->getRoutes();

    expect($routes->getByName(Handler::Login->value)->methods())->toContain('GET')
        ->and($routes->getByName(Handler::LoginStore->value)->methods())->toContain('POST')
        ->and($routes->getByName(Handler::Userinfo->value)->methods())->toContain('GET', 'POST')
        ->and($routes->getByName(Handler::Deny->value)->methods())->toContain('DELETE');
});

it('carries the configured middleware onto the registered route', function () {
    $routes = registerHandlersInFreshRouter()->getRoutes();

    expect($routes->getByName(Handler::LoginStore->value)->middleware())->toContain('throttle:5,1')
        ->and($routes->getByName(Handler::VerificationVerify->value)->middleware())->toContain('signed');
});

it('does not register a handler set to false', function () {
    $handlers = config('oidc.handlers');
    $handlers[Handler::Userinfo->value] = false;
    config(['oidc.handlers' => $handlers]);

    $routes = registerHandlersInFreshRouter()->getRoutes();

    expect($routes->getByName(Handler::Userinfo->value))->toBeNull()
        ->and($routes->getByName(Handler::Jwks->value))->not->toBeNull();
});

it('applies a custom route path, controller and middleware override', function () {
    $handlers = config('oidc.handlers');
    $handlers[Handler::Login->value] = [
        'route' => 'signin',
        'controller' => JwksController::class,
        'middleware' => ['web', 'my-guard'],
    ];
    config(['oidc.handlers' => $handlers]);

    $route = registerHandlersInFreshRouter()->getRoutes()->getByName(Handler::Login->value);

    expect($route->uri())->toBe('signin')
        ->and($route->getActionName())->toBe(JwksController::class)
        ->and($route->middleware())->toBe(['web', 'my-guard']);
});

it('exposes a handler config DTO through the facade', function () {
    $config = Oidc::handlerConfig(Handler::Login);

    expect($config)->toBeInstanceOf(HandlerConfig::class)
        ->and($config->route)->toBe('login')
        ->and($config->controller)->toBe([AuthenticatedSessionController::class, 'create'])
        ->and($config->middleware)->toBe(['web', 'guest:web']);
});

it('returns false from the facade for a disabled handler', function () {
    $handlers = config('oidc.handlers');
    $handlers[Handler::Userinfo->value] = false;
    config(['oidc.handlers' => $handlers]);

    expect(Oidc::handlerConfig(Handler::Userinfo))->toBeFalse();
});

it('exposes the issuer url through the facade', function () {
    config(['oidc.issuer' => 'https://id.example.com/']);

    expect(Oidc::issuer())->toBe('https://id.example.com');
});
