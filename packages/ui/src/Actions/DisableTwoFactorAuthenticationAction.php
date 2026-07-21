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

#[AsAction('oidc.two-factor.disable')]
class DisableTwoFactorAuthenticationAction extends ActionDefinition
{
    use ManagesTwoFactor;

    public function __construct(private readonly TwoFactorManager $twoFactor) {}

    public function definition(ActionComponent $action): ActionComponent
    {
        return $action
            ->label(__('oidc-ui::security.two-factor.disable'))
            ->method(HttpMethod::Post)
            ->variant(ButtonVariant::Outline)
            ->confirm(
                title: __('oidc-ui::security.two-factor.disable-confirm-title'),
                description: __('oidc-ui::security.two-factor.disable-confirm-description'),
                confirmLabel: __('oidc-ui::security.two-factor.disable'),
            );
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->twoFactorUser();

        $this->twoFactor->disable($user);

        return ActionResult::success()
            ->toast(__('oidc-ui::security.two-factor.disabled-toast'), Variant::Success)
            ->reloadPage();
    }
}
