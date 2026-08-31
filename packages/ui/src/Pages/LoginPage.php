<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Server\Auth\Views\LoginPrompt;
use Bambamboole\LaravelOidc\Server\Auth\Views\LoginView;
use Bambamboole\LaravelOidc\Ui\Components\PasskeyVerify;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lattice\Form\Components\Checkbox;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\PasswordInput;
use Lattice\Form\Components\TextInput;
use Lattice\Ui\Components\Button;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Grid;
use Lattice\Ui\Components\Link;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\StackDirection;
use Lattice\Ui\PageSchema;
use Symfony\Component\HttpFoundation\Response;

class LoginPage extends AuthPage implements LoginView
{
    final public function __construct(
        protected readonly ?LoginPrompt $prompt = null,
    ) {}

    public function respond(LoginPrompt $prompt, Request $request): Responsable|Response
    {
        return (new static($prompt))->toResponse($request);
    }

    public function title(): string
    {
        return __('oidc-ui::auth.login.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        $passkey = PasskeyVerify::makeIfAvailable('identity.passkey.login-options', 'identity.passkey.login');

        return $schema->schema([
            $this->heading('login-heading', __('oidc-ui::auth.login.heading'), __('oidc-ui::auth.login.subtitle')),
            ...($passkey === null ? [] : [$passkey]),
            Form::make('login-form')
                ->action(route('identity.login.store', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($this->formSchema())
                ->resetOnSuccess(['password'])
                ->withoutSubmitButton()
                ->status($this->prompt?->status),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private function formSchema(): array
    {
        $email = $this->emailField();

        $password = $this->passwordInput();

        $schema = [
            Grid::make('login-fields')
                ->columns(1)
                ->schema([
                    $email,
                    $password,
                    Checkbox::make('remember', __('oidc-ui::auth.login.remember')),
                ]),
            Button::make(__('oidc-ui::common.action.log-in'))->submit(),
        ];

        // A host application can disable the register handler; the sign-up
        // prompt would then link to a route that does not exist.
        if (Route::has('identity.register')) {
            $schema[] = Stack::make('login-register-prompt')
                ->align(Align::Center)
                ->direction(StackDirection::Row)
                ->gap(Gap::ExtraSmall)
                ->schema([
                    Text::make(__('oidc-ui::auth.login.no-account')),
                    Link::make(__('oidc-ui::auth.login.sign-up'))
                        ->href(route('identity.register', absolute: false)),
                ]);
        }

        return $schema;
    }

    protected function emailField(): TextInput
    {
        return TextInput::make('email', __('oidc-ui::common.field.email-address'))
            ->email()
            ->autoComplete('email')
            ->autoFocus()
            ->placeholder(__('oidc-ui::common.placeholder.email'))
            ->required();
    }

    protected function passwordInput(): PasswordInput
    {
        return PasswordInput::make('password', __('oidc-ui::common.field.password'))
            ->autoComplete('current-password')
            ->placeholder(__('oidc-ui::common.placeholder.password'))
            ->required()
            ->labelAction(Link::make(__('oidc-ui::auth.login.forgot-password'))->href(route('identity.password.request', absolute: false)));
    }
}
