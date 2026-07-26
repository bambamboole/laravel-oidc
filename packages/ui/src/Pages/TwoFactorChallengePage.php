<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Server\Auth\Views\TwoFactorChallengePrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\TwoFactorChallengeView;
use Bambamboole\LaravelOidc\Ui\Components\PasskeyVerify;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Checkbox;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\OtpInput;
use Lattice\Lattice\Forms\Components\TextInput;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorChallengePage extends AuthPage implements TwoFactorChallengeView
{
    public function __construct(
        private readonly ?TwoFactorChallengePrompt $prompt = null,
    ) {}

    public function respond(TwoFactorChallengePrompt $prompt, Request $request): Responsable|Response
    {
        return (new self($prompt))->toResponse($request);
    }

    public function title(): string
    {
        return __('oidc-ui::auth.two-factor.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        $webauthn = $this->prompt?->factor === 'webauthn'
            && Route::has('identity.two-factor.login.options')
            && Route::has('identity.two-factor.login.store');

        return $schema->schema([
            $this->heading('two-factor-challenge-heading', __('oidc-ui::auth.two-factor.heading'), $webauthn
                ? __('oidc-ui::auth.two-factor.subtitle-passkey')
                : __('oidc-ui::auth.two-factor.subtitle')),
            ...($webauthn ? [
                PasskeyVerify::make(
                    route('identity.two-factor.login.options', absolute: false),
                    route('identity.two-factor.login.store', absolute: false),
                )
                    ->label(__('oidc-ui::auth.two-factor.passkey-label'))
                    ->loadingLabel(__('oidc-ui::auth.two-factor.passkey-loading'))
                    ->separator(__('oidc-ui::auth.two-factor.passkey-separator')),
            ] : []),
            Form::make('two-factor-challenge')
                ->action(route('identity.two-factor.login.store', absolute: false))
                ->submitLabel(__('oidc-ui::auth.two-factor.continue'))
                ->schema($webauthn ? [
                    TextInput::make('recovery_code', __('oidc-ui::auth.two-factor.recovery-code'))
                        ->helperText(__('oidc-ui::auth.two-factor.recovery-help')),
                ] : [
                    OtpInput::make('code', __('oidc-ui::auth.two-factor.code'))
                        ->length(6)
                        ->visibleWhen('use_recovery_code', false),
                    TextInput::make('recovery_code', __('oidc-ui::auth.two-factor.recovery-code'))
                        ->helperText(__('oidc-ui::auth.two-factor.recovery-help'))
                        ->visibleWhen('use_recovery_code', true),
                    Checkbox::make('use_recovery_code', __('oidc-ui::auth.two-factor.use-recovery')),
                ]),
        ]);
    }
}
