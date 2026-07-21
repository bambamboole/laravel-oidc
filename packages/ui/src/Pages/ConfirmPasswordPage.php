<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Ui\Components\PasskeyVerify;
use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\PasswordInput;
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

#[AsPage(layout: PageLayout::Auth, container: PageContainer::Default)]
class ConfirmPasswordPage extends Page
{
    public function title(): string
    {
        return __('oidc-ui::auth.confirm-password.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        return $schema->schema([
            Stack::make('confirm-password-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::auth.confirm-password.heading'), 2),
                    Text::make(__('oidc-ui::auth.confirm-password.subtitle'))
                        ->align(Align::Center),
                ]),
            PasskeyVerify::make(
                route('identity.passkey.confirm-options', absolute: false),
                route('identity.passkey.confirm', absolute: false),
            )
                ->label(__('oidc-ui::auth.confirm-password.passkey-label'))
                ->loadingLabel(__('oidc-ui::auth.confirm-password.passkey-loading'))
                ->separator(__('oidc-ui::auth.confirm-password.passkey-separator')),
            Form::make('confirm-password-form')
                ->action(route('identity.password.confirm.store', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($this->formSchema())
                ->resetOnSuccess(['password'])
                ->withoutSubmitButton(),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private function formSchema(): array
    {
        return [
            Grid::make('confirm-password-fields')
                ->columns(1)
                ->schema([
                    PasswordInput::make('password', __('oidc-ui::common.field.password'))
                        ->autoComplete('current-password')
                        ->autoFocus()
                        ->placeholder(__('oidc-ui::common.placeholder.password'))
                        ->required(),
                ]),
            Button::make(__('oidc-ui::auth.confirm-password.submit'))->submit(),
        ];
    }
}
