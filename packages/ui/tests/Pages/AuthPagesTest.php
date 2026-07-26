<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\Views\LoginPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\LoginView;
use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordResetPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordResetRequestPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\TwoFactorChallengePrompt;
use Bambamboole\LaravelOidc\Server\Routing\Handler;
use Bambamboole\LaravelOidc\Server\Routing\HandlerRegistrar;
use Bambamboole\LaravelOidc\Ui\Pages\ConfirmPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\ForgotPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\LoginPage;
use Bambamboole\LaravelOidc\Ui\Pages\RegisterPage;
use Bambamboole\LaravelOidc\Ui\Pages\ResetPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\TwoFactorChallengePage;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

/**
 * Re-registers the identity routes with the given handlers disabled, swapping
 * both the router and the URL generator's collection so route() and
 * Route::has() agree on what exists.
 *
 * @param  list<Handler>  $disabled
 */
function withDisabledHandlers(array $disabled): void
{
    config(['oidc.handlers' => array_fill_keys(array_map(fn (Handler $handler) => $handler->value, $disabled), false)]);

    $router = new Router(new Dispatcher, app());
    Route::swap($router);
    app(HandlerRegistrar::class)->register();
    $router->getRoutes()->refreshNameLookups();
    app('url')->setRoutes($router->getRoutes());
}

function renderPage(object $page): string
{
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    return (string) $page->toResponse($request)->getContent();
}

/**
 * Every auth view contract renders through the server package's real
 * `identity.*` routes, resolved from the container. Requests are sent with
 * the `X-Inertia` header so Inertia returns the page payload as JSON instead
 * of trying to render a host application's root Blade view (which this
 * package does not ship), and `assertSee(..., false)` checks the payload for
 * the translated string without HTML-escaping the expectation first.
 */
it('renders the login page', function () {
    $this->get(route('identity.login'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.login.title'), false);
});

it('offers passkey sign-in on the login page when the handlers are registered', function () {
    expect(renderPage(new LoginPage))->toContain('passkey-verify');
});

it('renders the login page without passkeys when the passkey handlers are disabled', function () {
    withDisabledHandlers([Handler::PasskeyLoginOptions, Handler::PasskeyLogin]);

    expect(renderPage(new LoginPage))->not->toContain('passkey-verify');
});

it('renders the confirm-password page without passkeys when the passkey handlers are disabled', function () {
    withDisabledHandlers([Handler::PasskeyConfirmOptions, Handler::PasskeyConfirm]);

    expect(renderPage(new ConfirmPasswordPage))->not->toContain('passkey-verify');
});

it('offers the passkey ceremony on the two-factor challenge for a webauthn factor', function () {
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $content = (string) (new TwoFactorChallengePage)
        ->respond(new TwoFactorChallengePrompt(factor: 'webauthn'), $request)
        ->getContent();

    expect($content)->toContain('passkey-verify');
});

it('renders the code form without the passkey ceremony for a totp factor', function () {
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    $content = (string) (new TwoFactorChallengePage)
        ->respond(new TwoFactorChallengePrompt(factor: 'totp'), $request)
        ->getContent();

    expect($content)->not->toContain('passkey-verify')
        ->and($content)->toContain('two-factor-challenge');
});

it('renders the confirm-password page with the passkey ceremony when the handlers are registered', function () {
    expect(renderPage(new ConfirmPasswordPage))->toContain('passkey-verify');
});

it('offers only the recovery-code input on the two-factor challenge for a webauthn factor', function () {
    // The totp render anchors the `field.otp` marker so the not-contains
    // below cannot pass vacuously if the field type string ever changes.
    $totp = renderPage(new TwoFactorChallengePage(new TwoFactorChallengePrompt(factor: 'totp')));
    $webauthn = renderPage(new TwoFactorChallengePage(new TwoFactorChallengePrompt(factor: 'webauthn')));

    expect($totp)->toContain('field.otp')
        ->and($webauthn)->toContain('recovery_code')
        ->and($webauthn)->not->toContain('field.otp')
        ->and($webauthn)->not->toContain('use_recovery_code');
});

it('links back to the login page from the register page', function () {
    $content = renderPage(new RegisterPage);

    expect($content)->toContain(__('oidc-ui::auth.register.have-account'))
        ->and($content)->toContain(__('oidc-ui::common.action.log-in'));
});

it('threads the prompt status through the forgot-password form', function () {
    $content = renderPage(new ForgotPasswordPage(new PasswordResetRequestPrompt(status: 'A reset link was sent to your inbox.')));

    expect($content)->toContain('A reset link was sent to your inbox.');
});

it('prefills the reset-password form with the prompt token and email', function () {
    $content = renderPage(new ResetPasswordPage(new PasswordResetPrompt(token: 'reset-token-123', email: 'reset-user@example.com')));

    expect($content)->toContain('reset-token-123')
        ->and($content)->toContain('reset-user@example.com');
});

it('renders the register page', function () {
    $this->get(route('identity.register'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.register.title'), false);
});

it('renders the forgot-password page', function () {
    $this->get(route('identity.password.request'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.forgot-password.title'), false);
});

it('renders the reset-password page', function () {
    $this->get(route('identity.password.reset', ['token' => 'dummy-token']), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.reset-password.title'), false);
});

it('renders the verify-email page for an unverified authenticated user', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user, 'identity')
        ->get(route('identity.verification.notice'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.verify-email.title'), false);
});

it('shows the log-out link on the verify-email page when the logout route exists', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user, 'identity')
        ->get(route('identity.verification.notice'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::common.action.log-out'), false);
});

it('omits the log-out link on the verify-email page when the configured logout route does not exist', function () {
    config(['oidc-ui.logout_route' => 'route-that-does-not-exist']);

    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user, 'identity')
        ->get(route('identity.verification.notice'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertDontSee(__('oidc-ui::common.action.log-out'), false);
});

it('renders the confirm-password page for an authenticated user', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user, 'identity')
        ->get(route('identity.password.confirm'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.confirm-password.title'), false);
});

it('renders the two-factor challenge page for a pending login', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->withSession(['login.id' => $user->getAuthIdentifier()])
        ->get(route('identity.two-factor.login'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee(__('oidc-ui::auth.two-factor.title'), false);
});

it('lets a host application override a bound view contract', function () {
    $this->app->bind(LoginView::class, fn () => new class implements LoginView
    {
        public function respond(LoginPrompt $prompt, Request $request): JsonResponse
        {
            return response()->json(['view' => 'fake-login']);
        }
    });

    $this->get(route('identity.login'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertExactJson(['view' => 'fake-login']);
});
