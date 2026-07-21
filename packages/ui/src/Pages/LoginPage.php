<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Ui\Components\PasskeyVerify;
use Bambamboole\LaravelOidc\Ui\Concerns\ResolvesFlashStatus;
use Illuminate\Http\Request;
use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Checkbox;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\PasswordInput;
use Lattice\Lattice\Forms\Components\TextInput;
use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Button;
use Lattice\Lattice\Ui\Components\Component;
use Lattice\Lattice\Ui\Components\Grid;
use Lattice\Lattice\Ui\Components\Heading;
use Lattice\Lattice\Ui\Components\Link;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Lattice\Lattice\Ui\Enums\PageLayout;
use Lattice\Lattice\Ui\Enums\StackDirection;

#[AsPage(layout: PageLayout::Auth, container: PageContainer::Default)]
class LoginPage extends Page
{
    use ResolvesFlashStatus;

    public function title(): string
    {
        return __('oidc-ui::auth.login.title');
    }

    public function render(PageSchema $schema, Request $request): PageSchema
    {
        return $schema->schema([
            Stack::make('login-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::auth.login.heading'), 2),
                    Text::make(__('oidc-ui::auth.login.subtitle'))
                        ->align(Align::Center),
                ]),
            PasskeyVerify::make(
                route('identity.passkey.login-options', absolute: false),
                route('identity.passkey.login', absolute: false),
            ),
            Form::make('login-form')
                ->action(route('identity.login.store', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($this->formSchema())
                ->resetOnSuccess(['password'])
                ->withoutSubmitButton()
                ->status($this->flashStatus($request)),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private function formSchema(): array
    {
        $email = TextInput::make('email', __('oidc-ui::common.field.email-address'))
            ->email()
            ->autoComplete('email')
            ->autoFocus()
            ->placeholder(__('oidc-ui::common.placeholder.email'))
            ->required();

        $password = $this->passwordInput();

        return [
            Grid::make('login-fields')
                ->columns(1)
                ->schema([
                    $email,
                    $password,
                    Checkbox::make('remember', __('oidc-ui::auth.login.remember')),
                ]),
            Button::make(__('oidc-ui::common.action.log-in'))->submit(),
            Stack::make('login-register-prompt')
                ->align(Align::Center)
                ->direction(StackDirection::Row)
                ->gap(Gap::ExtraSmall)
                ->schema([
                    Text::make(__('oidc-ui::auth.login.no-account')),
                    Link::make(__('oidc-ui::auth.login.sign-up'))
                        ->href(route('identity.register', absolute: false)),
                ]),
        ];
    }

    private function passwordInput(): PasswordInput
    {
        return PasswordInput::make('password', __('oidc-ui::common.field.password'))
            ->autoComplete('current-password')
            ->placeholder(__('oidc-ui::common.placeholder.password'))
            ->required()
            ->labelAction(__('oidc-ui::auth.login.forgot-password'), route('identity.password.request', absolute: false));
    }
}
