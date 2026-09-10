<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Authentication\Views\EmailVerificationPrompt;
use Bambamboole\LaravelOidc\Server\Authentication\Views\LoginPrompt;
use Bambamboole\LaravelOidc\Server\Authentication\Views\LoginView;
use Bambamboole\LaravelOidc\Server\Authentication\Views\PasswordResetPrompt;
use Bambamboole\LaravelOidc\Server\Authentication\Views\PasswordResetRequestPrompt;
use Bambamboole\LaravelOidc\Server\Clients\Models\Client;
use Bambamboole\LaravelOidc\Server\Consents\Views\ConsentPrompt;
use Bambamboole\LaravelOidc\Server\Credentials\Views\TwoFactorChallengePrompt;
use Bambamboole\LaravelOidc\Ui\Pages\ConfirmPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\ForgotPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\LoginPage;
use Bambamboole\LaravelOidc\Ui\Pages\OAuthConsentPage;
use Bambamboole\LaravelOidc\Ui\Pages\RegisterPage;
use Bambamboole\LaravelOidc\Ui\Pages\ResetPasswordPage;
use Bambamboole\LaravelOidc\Ui\Pages\TwoFactorChallengePage;
use Bambamboole\LaravelOidc\Ui\Pages\VerifyEmailPage;
use Illuminate\Auth\GenericUser;
use Lattice\Form\Components\TextInput;

trait MarksItsOwnRender
{
    public function title(): string
    {
        return 'subclassed-page-marker';
    }
}

/**
 * Calls `respond()` on the page instance the way the server package does. The
 * prompt is spread so the two prompt-less pages go through the same helper.
 */
function respondWith(object $page, ?object $prompt = null): string
{
    return (string) $page
        ->respond(...[...($prompt === null ? [] : [$prompt]), inertiaRequest()])
        ->getContent();
}

/**
 * `respond()` must construct `new static`, not `new self`: with `new self` a
 * container-bound subclass renders as the base page. See
 * https://github.com/bambamboole/laravel-oidc/issues/87.
 */
it('renders the subclass, not the base page, for every auth page', function (): void {
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

    $rendered = array_map(
        fn (array $case): bool => str_contains(respondWith($case[0], $case[1]), 'subclassed-page-marker'),
        $cases,
    );

    expect($rendered)->toBe(array_fill_keys(array_keys($cases), true));
});

it('exposes the prompt it was constructed with to subclasses', function (): void {
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

it('honors a subclass bound to a view contract through the real route', function (): void {
    $this->app->bind(LoginView::class, fn (): LoginPage => new class extends LoginPage
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
