<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Auth\Views\ConsentPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\EmailVerificationPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\LoginPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\LoginView;
use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordResetPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordResetRequestPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\TwoFactorChallengePrompt;
use Bambamboole\LaravelOidc\Ui\Pages\ConfirmPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\ForgotPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\LoginPage;
use Bambamboole\LaravelOidc\Ui\Pages\OAuthConsentPage;
use Bambamboole\LaravelOidc\Ui\Pages\RegisterPage;
use Bambamboole\LaravelOidc\Ui\Pages\ResetPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\TwoFactorChallengePage;
use Bambamboole\LaravelOidc\Ui\Pages\VerifyEmailPage;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Lattice\Form\Components\TextInput;

/**
 * Overrides `title()` — public on every page — with a marker a subclass alone
 * can produce. Trait methods win over inherited ones, so this replaces the
 * base page's title without repeating the override per anonymous class.
 */
trait MarksItsOwnRender
{
    public function title(): string
    {
        return 'subclassed-page-marker';
    }
}

/**
 * Calls `respond()` the way the server package does — on the instance resolved
 * from the container — and returns the payload it rendered. `$prompt` is
 * spread so the two prompt-less pages work through the same helper.
 */
function respondWith(object $page, ?object $prompt = null): string
{
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    return (string) $page
        ->respond(...[...($prompt === null ? [] : [$prompt]), $request])
        ->getContent();
}

/**
 * `respond()` must construct `new static`, not `new self`: with `new self` the
 * container's binding is honored only long enough to enter `respond()`, and the
 * object that renders is always the base page — making a subclass a silent
 * no-op. See https://github.com/bambamboole/laravel-oidc/issues/87.
 */
it('renders the subclass, not the base page, for every auth page', function () {
    $cases = [
        'login' => [new class extends LoginPage
        {
            use MarksItsOwnRender;
        }, new LoginPrompt],
        'register' => [new class extends RegisterPage
        {
            use MarksItsOwnRender;
        }, null],
        'confirm-password' => [new class extends ConfirmPasswordPage
        {
            use MarksItsOwnRender;
        }, null],
        'forgot-password' => [new class extends ForgotPasswordPage
        {
            use MarksItsOwnRender;
        }, new PasswordResetRequestPrompt],
        'reset-password' => [new class extends ResetPasswordPage
        {
            use MarksItsOwnRender;
        }, new PasswordResetPrompt(token: 'reset-token-123')],
        'two-factor-challenge' => [new class extends TwoFactorChallengePage
        {
            use MarksItsOwnRender;
        }, new TwoFactorChallengePrompt(factor: 'totp')],
        'verify-email' => [new class extends VerifyEmailPage
        {
            use MarksItsOwnRender;
        }, new EmailVerificationPrompt],
        // Already correct before the fix; kept in the table so it stays that way.
        // The page only reads the client's name, so an unsaved model suffices.
        'oauth-consent' => [new class extends OAuthConsentPage
        {
            use MarksItsOwnRender;
        }, new ConsentPrompt(
            client: new Client(['name' => 'Test RP']),
            user: new GenericUser(['id' => 1]),
            scopes: [],
            authToken: 'auth-token-123',
        )],
    ];

    // Collected into a keyed map rather than asserted in the loop so a failure
    // names the page that discarded its subclass.
    $rendered = array_map(
        fn (array $case): bool => str_contains(respondWith($case[0], $case[1]), 'subclassed-page-marker'),
        $cases,
    );

    expect($rendered)->toBe(array_fill_keys(array_keys($cases), true));
});

it('lets a subclass read the prompt it was constructed with', function () {
    // A `private readonly $prompt` is out of scope here, so `??` would fall
    // through to the placeholder: the promoted property has to stay `protected`.
    $page = new class extends LoginPage
    {
        public function title(): string
        {
            return 'prompt-status:'.($this->prompt->status ?? 'unreadable');
        }
    };

    expect(respondWith($page, new LoginPrompt(status: 'Your session expired.')))
        ->toContain('prompt-status:Your session expired.');
});

it('honors a subclass bound to a view contract through the real route', function () {
    $this->app->bind(LoginView::class, fn () => new class extends LoginPage
    {
        protected function emailField(): TextInput
        {
            return parent::emailField()->value('dev@example.com');
        }
    });

    $this->get(route('identity.login'), ['X-Inertia' => 'true'])
        ->assertOk()
        ->assertSee('dev@example.com', false);
});
