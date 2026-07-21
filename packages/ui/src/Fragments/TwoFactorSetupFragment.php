<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Fragments;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Bambamboole\LaravelOidc\Ui\Concerns\ResolvesAuthenticatedUser;
use Bambamboole\LaravelOidc\Ui\Forms\ConfirmTwoFactorForm;
use Lattice\Lattice\Attributes\AsFragment;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Forms\Components\Form;
use Lattice\Lattice\Fragments\FragmentDefinition;
use Lattice\Lattice\Ui\Components\RawBlock;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;

#[AsFragment('oidc.two-factor-setup')]
class TwoFactorSetupFragment extends FragmentDefinition
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly TwoFactorManager $twoFactor,
        private readonly TotpFactorProvider $totp,
    ) {}

    public function schema(PageSchema $schema): PageSchema
    {
        $user = $this->currentUser();
        $factor = $this->twoFactor->currentFactor($user);

        if ($factor === null || $factor->confirmed_at !== null) {
            return $schema->schema([
                Text::make(__('oidc-ui::security.two-factor.already-enabled')),
            ]);
        }

        return $schema->schema([
            Stack::make('two-factor-setup')
                ->align(Align::Center)
                ->gap(Gap::Medium)
                ->schema([
                    RawBlock::make('two-factor-qr-code')->html($this->totp->qrCodeSvg($factor, $user)),
                    Text::make(__('oidc-ui::security.two-factor.setup-key')),
                    Text::make($factor->secret),
                    Form::use(ConfirmTwoFactorForm::class),
                ]),
        ]);
    }
}
