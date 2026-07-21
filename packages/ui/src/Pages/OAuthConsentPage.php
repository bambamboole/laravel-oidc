<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Pages;

use Bambamboole\LaravelOidc\Scopes\Scope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Client;
use Lattice\Lattice\Attributes\AsPage;
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

#[AsPage(layout: PageLayout::Auth, container: PageContainer::Default)]
class OAuthConsentPage extends Page
{
    /**
     * @param  array<int, Scope>  $scopes
     */
    public function __construct(
        private readonly Client $client,
        private readonly Authenticatable $user,
        private readonly array $scopes,
        private readonly string $authToken,
    ) {}

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
                            HiddenInput::make('auth_token')->value($this->authToken),
                            Button::make(__('oidc-ui::oauth.consent.approve'))->submit(),
                        ]),
                    Form::make('oauth-consent-deny')
                        ->action(route('oidc.deny', absolute: false))
                        ->method(HttpMethod::Delete)
                        ->withoutSubmitButton()
                        ->schema([
                            HiddenInput::make('auth_token')->value($this->authToken),
                            Button::make(__('oidc-ui::oauth.consent.deny'))->submit(),
                        ]),
                ]),
        ]);
    }

    private function clientName(): string
    {
        return (string) $this->client->getAttribute('name');
    }

    private function userEmail(): ?string
    {
        return $this->user instanceof Model ? $this->user->getAttribute('email') : null;
    }

    /**
     * @return array<int, Component>
     */
    private function scopeSchema(): array
    {
        if ($this->scopes === []) {
            return [Heading::make(__('oidc-ui::oauth.consent.requested-scopes'), 3)];
        }

        return [
            Heading::make(__('oidc-ui::oauth.consent.requested-scopes'), 3),
            ...array_map(
                fn (Scope $scope): Text => Text::make($scope->description),
                $this->scopes,
            ),
        ];
    }
}
