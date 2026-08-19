<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Support;

use Bambamboole\LaravelOidc\Ui\Fragments\RecoveryCodesFragment;
use Lattice\Fragments\Components\Fragment;
use Lattice\Ui\Components\Modal;

final class RecoveryCodesModal
{
    public const string DEFAULT_ID = 'oidc.recovery-codes';

    /**
     * The dialog that shows freshly issued recovery codes. Lattice's open-modal
     * effect carries the dialog itself, so the surfaces that issue codes ship
     * this node with the effect rather than reaching for one the host composed.
     * The id stays addressable so a host can close or restyle it.
     */
    public static function make(string $id = self::DEFAULT_ID): Modal
    {
        return Modal::make($id)
            ->title(__('oidc-ui::security.recovery-codes.heading'))
            ->schema([Fragment::lazy(RecoveryCodesFragment::class)]);
    }
}
