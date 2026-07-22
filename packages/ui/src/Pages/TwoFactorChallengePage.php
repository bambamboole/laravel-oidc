<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Auth\Views\TwoFactorChallengePrompt;
use Bambamboole\LaravelOidc\Auth\Views\TwoFactorChallengeView;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Checkbox;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\OtpInput;
use Lattice\Lattice\Forms\Components\TextInput;
use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Heading;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Lattice\Lattice\Ui\Enums\PageLayout;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorChallengePage extends Page implements TwoFactorChallengeView
{
    /**
     * {@see TwoFactorChallengePrompt} carries no data today, so there is
     * nothing to thread through the constructor — the page still resolves
     * with zero args, satisfying the container binding invariant.
     */
    public function respond(TwoFactorChallengePrompt $prompt, Request $request): Responsable|Response
    {
        return (new self)->toResponse($request);
    }

    public function layout(): PageLayout|string|null
    {
        return PageLayout::Auth;
    }

    public function container(): PageContainer|string|null
    {
        return PageContainer::Default;
    }

    public function title(): string
    {
        return __('oidc-ui::auth.two-factor.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        return $schema->schema([
            Stack::make('two-factor-challenge-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::auth.two-factor.heading'), 2),
                    Text::make(__('oidc-ui::auth.two-factor.subtitle'))
                        ->align(Align::Center),
                ]),
            Form::make('two-factor-challenge')
                ->action(route('identity.two-factor.login.store', absolute: false))
                ->submitLabel(__('oidc-ui::auth.two-factor.continue'))
                ->schema([
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
