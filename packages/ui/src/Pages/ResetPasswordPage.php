<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordResetPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordResetView;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\HiddenInput;
use Lattice\Lattice\Forms\Components\PasswordInput;
use Lattice\Lattice\Forms\Components\TextInput;
use Lattice\Lattice\Ui\Components\Button;
use Lattice\Lattice\Ui\Components\Component;
use Lattice\Lattice\Ui\Components\Grid;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordPage extends AuthPage implements PasswordResetView
{
    public function __construct(
        private readonly ?PasswordResetPrompt $prompt = null,
    ) {}

    public function respond(PasswordResetPrompt $prompt, Request $request): Responsable|Response
    {
        return (new self($prompt))->toResponse($request);
    }

    public function title(): string
    {
        return __('oidc-ui::auth.reset-password.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        $prompt = $this->prompt ?? throw new LogicException(self::class.' rendered without its prompt; respond() must supply one before render() runs.');
        $token = $prompt->token;
        $email = $prompt->email ?? '';

        return $schema->schema([
            $this->heading('reset-password-heading', __('oidc-ui::auth.reset-password.heading'), __('oidc-ui::auth.reset-password.subtitle')),
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
