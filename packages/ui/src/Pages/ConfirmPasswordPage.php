<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Server\Auth\Views\PasswordConfirmationView;
use Bambamboole\LaravelOidc\Ui\Components\PasskeyVerify;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\PasswordInput;
use Lattice\Ui\Components\Button;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Grid;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\PageSchema;
use Symfony\Component\HttpFoundation\Response;

class ConfirmPasswordPage extends AuthPage implements PasswordConfirmationView
{
    /**
     * `final` so `respond()` can construct `new static` without a subclass
     * being able to widen the signature into required arguments.
     */
    final public function __construct() {}

    public function respond(Request $request): Responsable|Response
    {
        return (new static)->toResponse($request);
    }

    public function title(): string
    {
        return __('oidc-ui::auth.confirm-password.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        return $schema->schema([
            $this->heading('confirm-password-heading', __('oidc-ui::auth.confirm-password.heading'), __('oidc-ui::auth.confirm-password.subtitle')),
            ...$this->passkeySchema(),
            Form::make('confirm-password-form')
                ->action(route('identity.password.confirm.store', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($this->formSchema())
                ->resetOnSuccess(['password'])
                ->withoutSubmitButton(),
        ]);
    }

    /**
     * The passkey handlers can be disabled via oidc.handlers; the page must
     * keep rendering without them.
     *
     * @return array<int, Component>
     */
    private function passkeySchema(): array
    {
        $passkey = PasskeyVerify::makeIfAvailable(
            'identity.passkey.confirm-options',
            'identity.passkey.confirm',
            label: __('oidc-ui::auth.confirm-password.passkey-label'),
            loadingLabel: __('oidc-ui::auth.confirm-password.passkey-loading'),
            separator: __('oidc-ui::auth.confirm-password.passkey-separator'),
        );

        return $passkey === null ? [] : [$passkey];
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
