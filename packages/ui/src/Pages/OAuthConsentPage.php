<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Auth\Views\ConsentPrompt;
use Bambamboole\LaravelOidc\Auth\Views\ConsentView;
use Bambamboole\LaravelOidc\Scopes\Scope;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Forms\Components\HiddenInput;
use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Button;
use Lattice\Lattice\Ui\Components\Component;
use Lattice\Lattice\Ui\Components\Heading;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Lattice\Lattice\Ui\Enums\PageLayout;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class OAuthConsentPage extends Page implements ConsentView
{
    public function __construct(
        private readonly ?ConsentPrompt $prompt = null,
    ) {}

    public function respond(ConsentPrompt $prompt, Request $request): Responsable|Response
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
        return __('oidc-ui::oauth.consent.heading', ['client' => $this->clientName()]);
    }

    public function render(PageSchema $schema): PageSchema
    {
        return $schema->schema([
            Stack::make('oauth-consent-heading')
                ->gap(Gap::Small)
                ->schema([
                    Heading::make(__('oidc-ui::oauth.consent.heading', ['client' => $this->clientName()]), 2),
                    Text::make(__('oidc-ui::oauth.consent.signed-in-as', ['email' => $this->userEmail()])),
                ]),
            Stack::make('oauth-consent-scopes')
                ->gap(Gap::Small)
                ->schema($this->scopeSchema()),
            Stack::make('oauth-consent-actions')
                ->gap(Gap::Small)
                ->schema([
                    Form::make('oauth-consent-approve')
                        ->action(route('oidc.approve', absolute: false))
                        ->method(HttpMethod::Post)
                        ->withoutSubmitButton()
                        ->schema([
                            HiddenInput::make('auth_token')->value($this->authToken()),
                            Button::make(__('oidc-ui::oauth.consent.approve'))->submit(),
                        ]),
                    Form::make('oauth-consent-deny')
                        ->action(route('oidc.deny', absolute: false))
                        ->method(HttpMethod::Delete)
                        ->withoutSubmitButton()
                        ->schema([
                            HiddenInput::make('auth_token')->value($this->authToken()),
                            Button::make(__('oidc-ui::oauth.consent.deny'))->submit(),
                        ]),
                ]),
        ]);
    }

    /**
     * The controller always resolves this page through respond(), which supplies the real
     * prompt before render() ever runs — a missing prompt here means that invariant broke.
     */
    private function prompt(): ConsentPrompt
    {
        return $this->prompt ?? throw new LogicException('OAuthConsentPage rendered without a ConsentPrompt; respond() must supply one before render() runs.');
    }

    private function clientName(): string
    {
        return (string) $this->prompt()->client->getAttribute('name');
    }

    private function userEmail(): string
    {
        $user = $this->prompt()->user;

        return $user instanceof Model ? (string) $user->getAttribute('email') : '';
    }

    private function authToken(): string
    {
        return $this->prompt()->authToken;
    }

    /**
     * @return array<int, Component>
     */
    private function scopeSchema(): array
    {
        $scopes = $this->prompt()->scopes;

        if ($scopes === []) {
            return [Heading::make(__('oidc-ui::oauth.consent.requested-scopes'), 3)];
        }

        return [
            Heading::make(__('oidc-ui::oauth.consent.requested-scopes'), 3),
            ...array_map(
                fn (Scope $scope): Text => Text::make($scope->description),
                $scopes,
            ),
        ];
    }
}
