<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Fragments;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\RecoveryCodeProvider;
use Lattice\Core\Attributes\AsFragment;
use Lattice\Fragments\FragmentDefinition;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\PageSchema;

/**
 * The user's unused recovery codes — the only shipped surface that renders
 * them, meant for the modal opened after a factor is confirmed or the codes
 * are regenerated.
 */
#[AsFragment('oidc.recovery-codes')]
class RecoveryCodesFragment extends FragmentDefinition
{
    public function __construct(private readonly RecoveryCodeProvider $recoveryCodes) {}

    public function schema(PageSchema $schema): PageSchema
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        $codes = $this->recoveryCodes->codes($user);

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
