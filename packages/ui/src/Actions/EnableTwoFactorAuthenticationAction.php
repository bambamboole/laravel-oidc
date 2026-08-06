<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Actions;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Bambamboole\LaravelOidc\Ui\Support\FactorMethodName;
use Illuminate\Http\Request;
use Lattice\Actions\ActionDefinition;
use Lattice\Actions\ActionResult;
use Lattice\Actions\Components\Action as ActionComponent;
use Lattice\Core\Attributes\AsAction;
use Lattice\Ui\Enums\HttpMethod;
use Lattice\Ui\Enums\Variant;

#[AsAction('oidc.two-factor.enable')]
class EnableTwoFactorAuthenticationAction extends ActionDefinition
{
    use ManagesTwoFactor;

    public function __construct(private readonly FactorRegistry $factors) {}

    public function definition(ActionComponent $action): ActionComponent
    {
        return $action
            ->label(__('oidc-ui::security.two-factor.enable', ['method' => FactorMethodName::for($this->providerKey())]))
            ->method(HttpMethod::Post);
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->twoFactorUser();

        ($this->factors->enrollable($this->providerKey()) ?? abort(404))->beginEnrollment($user);

        return ActionResult::success()
            ->toast(__('oidc-ui::security.two-factor.setup-started'), Variant::Info)
            ->openModal((string) $this->context('modal', 'oidc.two-factor-setup'));
    }
}
