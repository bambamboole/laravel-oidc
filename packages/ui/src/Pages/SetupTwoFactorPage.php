<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Server\Credentials\Views\FactorSetupPrompt;
use Bambamboole\LaravelOidc\Server\Credentials\Views\FactorSetupView;
use Bambamboole\LaravelOidc\Ui\Forms\TwoFactorSetupForm;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Lattice\Form\Components\Form;
use Lattice\Ui\Components\Button;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\PageSchema;
use Symfony\Component\HttpFoundation\Response;

class SetupTwoFactorPage extends AuthPage implements FactorSetupView
{
    final public function __construct(
        protected readonly ?FactorSetupPrompt $prompt = null,
    ) {}

    public function respond(FactorSetupPrompt $prompt, Request $request): Responsable|Response
    {
        return new static($prompt)->toResponse($request);
    }

    public function title(): string
    {
        return __('oidc-ui::auth.two-factor-setup.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        return $schema->schema([
            $this->heading(
                'two-factor-setup-heading',
                __('oidc-ui::auth.two-factor-setup.heading'),
                $this->prompt?->required === true
                    ? __('oidc-ui::auth.two-factor-setup.subtitle-required')
                    : __('oidc-ui::auth.two-factor-setup.subtitle'),
            ),
            Form::use(TwoFactorSetupForm::class),
            ...$this->continueButton(),
        ]);
    }

    /**
     * The wizard stays on this page after a confirmation so the recovery codes
     * it mints are seen before anything navigates away; continuing the held
     * login is therefore a deliberate step.
     *
     * @return array<int, Component>
     */
    private function continueButton(): array
    {
        if ($this->prompt?->required !== true || ! $this->prompt->enrolled) {
            return [];
        }

        return [
            Form::make('two-factor-setup-continue')
                ->action(route('identity.two-factor.setup.continue', absolute: false))
                ->method(HttpMethod::Post)
                ->schema([Button::make(__('oidc-ui::auth.two-factor-setup.continue'))->submit()])
                ->withoutSubmitButton(),
        ];
    }
}
