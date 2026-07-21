<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Actions;

use Bambamboole\LaravelOidc\Auth\MultiFactor\TwoFactorManager;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Illuminate\Http\Request;
use Lattice\Lattice\Actions\ActionDefinition;
use Lattice\Lattice\Actions\ActionResult;
use Lattice\Lattice\Actions\Components\Action as ActionComponent;
use Lattice\Lattice\Attributes\AsAction;
use Lattice\Lattice\Ui\Enums\ButtonVariant;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\Variant;

#[AsAction('oidc.two-factor.regenerate-recovery-codes')]
class RegenerateRecoveryCodesAction extends ActionDefinition
{
    use ManagesTwoFactor;

    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function definition(ActionComponent $action): ActionComponent
    {
        return $action
            ->label(__('oidc-ui::security.recovery-codes.regenerate'))
            ->method(HttpMethod::Post)
            ->variant(ButtonVariant::Secondary)
            ->confirm(
                title: __('oidc-ui::security.recovery-codes.regenerate-confirm-title'),
                description: __('oidc-ui::security.recovery-codes.regenerate-confirm-description'),
                confirmLabel: __('oidc-ui::security.recovery-codes.regenerate'),
            );
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->twoFactorUser();

        $this->twoFactor->regenerateRecoveryCodes($user);

        return ActionResult::success()
            ->toast(__('oidc-ui::security.recovery-codes.regenerated'), Variant::Success)
            ->reloadPage();
    }
}
