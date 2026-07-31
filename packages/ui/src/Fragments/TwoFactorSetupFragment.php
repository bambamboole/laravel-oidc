<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Fragments;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\EnrollableFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Ui\Concerns\ResolvesAuthenticatedUser;
use Bambamboole\LaravelOidc\Ui\Forms\ConfirmTwoFactorForm;
use Lattice\Lattice\Attributes\AsFragment;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Fragments\FragmentDefinition;
use Lattice\Lattice\Ui\Components\Component;
use Lattice\Lattice\Ui\Components\RawBlock;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;

#[AsFragment('oidc.two-factor-setup')]
class TwoFactorSetupFragment extends FragmentDefinition
{
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly FactorRegistry $factors) {}

    public function schema(PageSchema $schema): PageSchema
    {
        $user = $this->currentUser();
        $provider = $this->factors->enrollable((string) $this->context('provider', 'totp')) ?? abort(404);

        if ($provider instanceof TotpFactorProvider) {
            $factor = $provider->latestFactor($user);

            if ($factor === null || $factor->confirmed_at !== null) {
                return $schema->schema([
                    Text::make(__('oidc-ui::security.two-factor.already-enabled')),
                ]);
            }

            return $schema->schema([
                $this->setupStack([
                    RawBlock::make('two-factor-qr-code')->html($provider->qrCodeSvg($factor, $user)),
                    Text::make(__('oidc-ui::security.two-factor.setup-key')),
                    Text::make($factor->secret),
                ], $provider),
            ]);
        }

        $pending = null;
        foreach ($provider->enrollments($user) as $enrollment) {
            if ($enrollment->confirmedAt === null) {
                $pending = $enrollment;
            }
        }

        if (! $pending instanceof FactorEnrollment) {
            return $schema->schema([
                Text::make(__('oidc-ui::security.two-factor.already-enabled')),
            ]);
        }

        return $schema->schema([
            $this->setupStack([Text::make($pending->label)], $provider),
        ]);
    }

    /**
     * @param  list<Component>  $components
     */
    private function setupStack(array $components, EnrollableFactorProvider $provider): Stack
    {
        return Stack::make('two-factor-setup')
            ->align(Align::Center)
            ->gap(Gap::Medium)
            ->schema([
                ...$components,
                Form::use(ConfirmTwoFactorForm::class, ['provider' => $provider->key()]),
            ]);
    }
}
