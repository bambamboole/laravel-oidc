<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Actions;

use Bambamboole\LaravelOidc\Auth\MultiFactor\Contracts\FactorAuthenticatable;
use Bambamboole\LaravelOidc\Ui\Concerns\ResolvesAuthenticatedUser;
use Illuminate\Http\Request;
use Lattice\Lattice\Actions\ActionDefinition;
use Lattice\Lattice\Actions\ActionResult;
use Lattice\Lattice\Actions\Components\Action;
use Lattice\Lattice\Attributes\AsAction;
use Lattice\Lattice\Ui\Enums\ButtonVariant;
use Lattice\Lattice\Ui\Enums\Variant;

#[AsAction('oidc.passkeys.delete')]
class DeletePasskeyAction extends ActionDefinition
{
    use ResolvesAuthenticatedUser;

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('oidc-ui::security.passkeys.remove'))
            ->variant(ButtonVariant::Ghost)
            ->confirm(
                title: __('oidc-ui::security.passkeys.remove-confirm-title'),
                description: __('oidc-ui::security.passkeys.remove-confirm-description'),
                confirmLabel: __('oidc-ui::security.passkeys.remove'),
            );
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        $user = $this->currentUser();

        if (! $user instanceof FactorAuthenticatable) {
            return false;
        }

        return $user->passkeys()->whereKey($this->context('passkey'))->exists();
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->currentUser();

        abort_unless($user instanceof FactorAuthenticatable, 403);

        $user->passkeys()->whereKey($this->context('passkey'))->delete();

        return ActionResult::success()
            ->toast(__('oidc-ui::security.passkeys.removed'), Variant::Success)
            ->reloadComponent('oidc.passkeys');
    }
}
