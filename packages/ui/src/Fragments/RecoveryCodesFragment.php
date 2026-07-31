<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Fragments;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\RecoveryCodeProvider;
use Bambamboole\LaravelOidc\Ui\Concerns\ResolvesAuthenticatedUser;
use Lattice\Lattice\Attributes\AsFragment;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Fragments\FragmentDefinition;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Components\Text;
use Lattice\Lattice\Ui\Enums\Align;
use Lattice\Lattice\Ui\Enums\Gap;

/**
 * The user's unused recovery codes — the only shipped surface that renders
 * them, meant for the modal opened after a factor is confirmed or the codes
 * are regenerated.
 */
#[AsFragment('oidc.recovery-codes')]
class RecoveryCodesFragment extends FragmentDefinition
{
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly RecoveryCodeProvider $recoveryCodes) {}

    public function schema(PageSchema $schema): PageSchema
    {
        $codes = $this->recoveryCodes->codes($this->currentUser());

        if ($codes === []) {
            return $schema->schema([
                Text::make(__('oidc-ui::security.recovery-codes.none')),
            ]);
        }

        return $schema->schema([
            Stack::make('recovery-codes')
                ->align(Align::Center)
                ->gap(Gap::Small)
                ->schema([
                    Text::make(__('oidc-ui::security.recovery-codes.description')),
                    ...array_map(
                        static fn (string $code) => Text::make($code)->copyable(),
                        $codes,
                    ),
                ]),
        ]);
    }
}
