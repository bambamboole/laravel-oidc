<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
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
 * Route::has() agree on what exists. Non-identity routes are carried over from
 * the original router: packages resolve their own routes while rendering (e.g.
 * lattice's refresh endpoint), so only the identity routes may be rebuilt.
 *
 * @param  list<Handler>  $disabled
 */
function withDisabledHandlers(array $disabled): void
{
    config(['oidc.handlers' => array_fill_keys(array_map(fn (Handler $handler) => $handler->value, $disabled), false)]);

    $previous = Route::getRoutes();
    $router = new Router(new Dispatcher, app());
    Route::swap($router);
    app(HandlerRegistrar::class)->register();

    foreach ($previous->getRoutes() as $route) {
        if (! str_starts_with((string) $route->getName(), 'identity.')) {
            $router->getRoutes()->add($route);
        }
    }

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
 * The `two-factor-challenge` form's fields, keyed by input name, as the client
 * receives them. The recovery-code reveal is a client-side toggle driven by
 * server-declared `conditions` metadata, so the payload is where its wiring can
 * be asserted without a browser.
 *
 * @return array<string, array<string, mixed>>
 */
function twoFactorChallengeFields(TwoFactorChallengePrompt $prompt): array
{
    $payload = json_decode(renderPage(new TwoFactorChallengePage($prompt)), true, flags: JSON_THROW_ON_ERROR);
    $schema = data_get($payload, 'props.lattice.schema');
    $form = collect(is_array($schema) ? $schema : [])->firstWhere('id', 'two-factor-challenge');
    $fields = data_get($form, 'schema');

    return collect(is_array($fields) ? $fields : [])
        ->mapWithKeys(fn (mixed $field): array => [(string) data_get($field, 'props.name') => (array) data_get($field, 'props')])
        ->all();
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

it('offers the sign-up prompt on the login page when the register handler is registered', function () {
    expect(renderPage(new LoginPage))->toContain(__('oidc-ui::auth.login.sign-up'));
});

it('renders the login page without the sign-up prompt when the register handler is disabled', function () {
    withDisabledHandlers([Handler::Register, Handler::RegisterStore]);

    expect(renderPage(new LoginPage))->not->toContain(__('oidc-ui::auth.login.sign-up'));
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

it('wires the recovery-code reveal toggle on a code-based challenge', function () {
    $fields = twoFactorChallengeFields(new TwoFactorChallengePrompt(factor: 'totp'));

    expect(array_keys($fields))->toBe(['code', 'recovery_code', 'use_recovery_code'])
        // The checkbox drives the reveal, so it must stay unconditional itself —
        // a condition on it could hide the only way back to the code input.
        ->and($fields['use_recovery_code']['conditions'])->toBeNull()
        // Exactly inverse conditions: one field is showing at any time.
        ->and($fields['code']['conditions'])->toMatchArray([
            'visible' => [['field' => 'use_recovery_code', 'operator' => 'eq', 'value' => false]],
        ])
        ->and($fields['recovery_code']['conditions'])->toMatchArray([
            'visible' => [['field' => 'use_recovery_code', 'operator' => 'eq', 'value' => true]],
        ]);
});

it('reveals the recovery-code input unconditionally on a webauthn challenge', function () {
    // The passkey ceremony replaces the code input, so there is nothing to
    // toggle between and the recovery code must be visible outright.
    $fields = twoFactorChallengeFields(new TwoFactorChallengePrompt(factor: 'webauthn'));

    expect(array_keys($fields))->toBe(['recovery_code'])
        ->and($fields['recovery_code']['conditions'])->toBeNull();
});

it('offers the other enrolled methods on a multi-provider challenge', function () {
    $content = renderPage(new TwoFactorChallengePage(new TwoFactorChallengePrompt(factor: 'totp', availableFactors: [
        new FactorEnrollment('totp', '1', 'Authenticator', now(), null),
        new FactorEnrollment('webauthn', '2', 'Security key', now(), null),
    ])));

    expect($content)->toContain(__('oidc-ui::auth.two-factor.use-another'))
        ->and($content)->toContain(__('oidc-ui::auth.two-factor.method.webauthn'))
        // Inertia JSON escapes forward slashes, so match the escaped href.
        ->and($content)->toContain('two-factor-challenge\/factor\/webauthn')
        ->and($content)->not->toContain('two-factor-challenge\/factor\/totp');
});

it('renders no method switcher for a single-provider challenge', function () {
    $content = renderPage(new TwoFactorChallengePage(new TwoFactorChallengePrompt(factor: 'totp', availableFactors: [
        new FactorEnrollment('totp', '1', 'Authenticator', now(), null),
    ])));

    expect($content)->not->toContain(__('oidc-ui::auth.two-factor.use-another'));
});

it('falls back to the code form and raw key label for an unknown provider', function () {
    $content = renderPage(new TwoFactorChallengePage(new TwoFactorChallengePrompt(factor: 'sms', availableFactors: [
        new FactorEnrollment('sms', '1', 'Phone', now(), null),
        new FactorEnrollment('totp', '2', 'Authenticator', now(), null),
    ])));

    expect($content)->toContain('field.otp')
        ->and($content)->not->toContain('passkey-verify')
        ->and($content)->toContain(__('oidc-ui::auth.two-factor.method.totp'));
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

    expect($content)->toContain('"name":"token"')
        ->and($content)->toContain('"value":"reset-token-123"')
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

    $this->withSession(['login.id' => $user->getAuthIdentifier(), 'login.factor' => 'totp'])
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
