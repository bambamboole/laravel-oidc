<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Auth\Views\RegisterPrompt;
use Bambamboole\LaravelOidc\Auth\Views\RegisterView;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
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
use Symfony\Component\HttpFoundation\Response;

class RegisterPage extends Page implements RegisterView
{
    /**
     * {@see RegisterPrompt} carries no data today, so there is nothing to
     * thread through the constructor — every page still resolves with zero
     * args, satisfying the container binding invariant.
     */
    public function respond(RegisterPrompt $prompt, Request $request): Responsable|Response
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
        return __('oidc-ui::auth.register.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        return $schema->schema([
            Stack::make('register-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::auth.register.heading'), 2),
                    Text::make(__('oidc-ui::auth.register.subtitle'))
                        ->align(Align::Center),
                ]),
            Form::make('register-form')
                ->action(route('identity.register.store', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($this->formSchema())
                ->resetOnSuccess(['password', 'password_confirmation'])
                ->withoutSubmitButton(),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private function formSchema(): array
    {
        return [
            Grid::make('register-fields')
                ->columns(1)
                ->schema([
                    TextInput::make('name', __('oidc-ui::common.field.name'))
                        ->autoComplete('name')
                        ->autoFocus()
                        ->placeholder(__('oidc-ui::common.placeholder.full-name'))
                        ->required(),
                    TextInput::make('email', __('oidc-ui::common.field.email-address'))
                        ->email()
                        ->autoComplete('email')
                        ->placeholder(__('oidc-ui::common.placeholder.email'))
                        ->required(),
                    PasswordInput::make('password', __('oidc-ui::common.field.password'))
                        ->autoComplete('new-password')
                        ->placeholder(__('oidc-ui::common.placeholder.password'))
                        ->required()
                        ->needsConfirmation(),
                ]),
            Button::make(__('oidc-ui::auth.register.submit'))->submit(),
            Stack::make('register-login-prompt')
                ->align(Align::Center)
                ->direction(StackDirection::Row)
                ->gap(Gap::ExtraSmall)
                ->schema([
                    Text::make(__('oidc-ui::auth.register.have-account')),
                    Link::make(__('oidc-ui::common.action.log-in'))
                        ->href(route('identity.login', absolute: false)),
                ]),
        ];
    }
}
