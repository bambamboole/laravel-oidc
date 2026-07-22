<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Auth\Views\PasswordResetPrompt;
use Bambamboole\LaravelOidc\Auth\Views\PasswordResetView;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\HiddenInput;
use Lattice\Lattice\Forms\Components\PasswordInput;
use Lattice\Lattice\Forms\Components\TextInput;
use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Button;
use Lattice\Lattice\Ui\Components\Component;
use Lattice\Lattice\Ui\Components\Grid;
use Lattice\Lattice\Ui\Components\Heading;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Lattice\Lattice\Ui\Enums\PageLayout;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordPage extends Page implements PasswordResetView
{
    public function __construct(
        private readonly ?PasswordResetPrompt $prompt = null,
    ) {}

    public function respond(PasswordResetPrompt $prompt, Request $request): Responsable|Response
    {
        return (new self($prompt))->toResponse($request);
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
        return __('oidc-ui::auth.reset-password.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        $token = $this->prompt->token;
        $email = $this->prompt->email ?? '';

        return $schema->schema([
            Stack::make('reset-password-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::auth.reset-password.heading'), 2),
                    Text::make(__('oidc-ui::auth.reset-password.subtitle'))->align(Align::Center),
                ]),
            Form::make('reset-password-form')
                ->action(route('identity.password.update', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($this->formSchema($token, $email))
                ->resetOnSuccess(['password', 'password_confirmation'])
                ->withoutSubmitButton(),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private function formSchema(string $token, string $email): array
    {
        return [
            Grid::make('reset-password-fields')
                ->columns(1)
                ->schema([
                    HiddenInput::make('token', $token),
                    TextInput::make('email', __('oidc-ui::common.field.email-address'))
                        ->email()
                        ->autoComplete('email')
                        ->value($email)
                        ->readOnly()
                        ->required(),
                    PasswordInput::make('password', __('oidc-ui::common.field.password'))
                        ->autoComplete('new-password')
                        ->autoFocus()
                        ->placeholder(__('oidc-ui::common.placeholder.password'))
                        ->required()
                        ->needsConfirmation(),
                ]),
            Button::make(__('oidc-ui::auth.reset-password.submit'))->submit(),
        ];
    }
}
