<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Auth\Views\EmailVerificationPrompt;
use Bambamboole\LaravelOidc\Auth\Views\EmailVerificationView;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Button;
use Lattice\Lattice\Ui\Components\Heading;
use Lattice\Lattice\Ui\Components\Link;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Lattice\Lattice\Ui\Enums\PageLayout;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailPage extends Page implements EmailVerificationView
{
    public function __construct(
        private readonly ?EmailVerificationPrompt $prompt = null,
    ) {}

    public function respond(EmailVerificationPrompt $prompt, Request $request): Responsable|Response
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
        return __('oidc-ui::auth.verify-email.title');
    }

    public function render(PageSchema $schema): PageSchema
    {
        $formSchema = [
            Button::make(__('oidc-ui::auth.verify-email.resend'))->submit(),
        ];

        $logoutRoute = config('oidc-ui.logout_route', 'logout');

        if (Route::has($logoutRoute)) {
            $formSchema[] = Link::make(__('oidc-ui::common.action.log-out'))
                ->href(route($logoutRoute, absolute: false))
                ->method(HttpMethod::Post);
        }

        return $schema->schema([
            Stack::make('verify-email-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::auth.verify-email.heading'), 2),
                    Text::make(__('oidc-ui::auth.verify-email.subtitle'))
                        ->align(Align::Center),
                ]),
            Form::make('verify-email-form')
                ->action(route('identity.verification.send', absolute: false))
                ->method(HttpMethod::Post)
                ->schema($formSchema)
                ->withoutSubmitButton()
                ->status($this->statusMessage()),
        ]);
    }

    private function statusMessage(): ?string
    {
        if ($this->prompt?->status !== 'verification-link-sent') {
            return null;
        }

        return __('oidc-ui::auth.verify-email.sent');
    }
}
