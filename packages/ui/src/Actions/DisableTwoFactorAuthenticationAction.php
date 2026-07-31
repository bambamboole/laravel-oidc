<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Actions;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\EnrollableFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\EnrollmentPolicy;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\WebAuthnFactorProvider;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Illuminate\Http\Request;
use Lattice\Lattice\Actions\ActionDefinition;
use Lattice\Lattice\Actions\ActionResult;
use Lattice\Lattice\Actions\Components\Action as ActionComponent;
use Lattice\Lattice\Attributes\AsAction;
use Lattice\Lattice\Ui\Enums\Emphasis;
use Lattice\Lattice\Ui\Enums\HttpMethod;
use Lattice\Lattice\Ui\Enums\Variant;

#[AsAction('oidc.two-factor.disable')]
class DisableTwoFactorAuthenticationAction extends ActionDefinition
{
    use ManagesTwoFactor;

    public function __construct(
        private readonly FactorRegistry $factors,
        private readonly EnrollmentPolicy $policy,
    ) {}

    public function definition(ActionComponent $action): ActionComponent
    {
        return $action
            ->label(__('oidc-ui::security.two-factor.disable'))
            ->method(HttpMethod::Post)
            ->emphasis(Emphasis::Outline)
            ->confirm(
                title: __('oidc-ui::security.two-factor.disable-confirm-title'),
                description: __('oidc-ui::security.two-factor.disable-confirm-description'),
                confirmLabel: __('oidc-ui::security.two-factor.disable'),
            );
    }

    /**
     * Disabling revokes every enrollment of every enrollable provider —
     * except passkeys, which double as a first-factor sign-in method and are
     * removed individually instead.
     */
    public function handle(Request $request): ActionResult
    {
        $user = $this->twoFactorUser();

        foreach ($this->factors->providers() as $provider) {
            if (! $provider instanceof EnrollableFactorProvider || $provider instanceof WebAuthnFactorProvider) {
                continue;
            }

            foreach ($provider->enrollments($user) as $enrollment) {
                $provider->revoke($user, $enrollment);
            }
        }

        $this->policy->factorRevoked($user);

        return ActionResult::success()
            ->toast(__('oidc-ui::security.two-factor.disabled-toast'), Variant::Success)
            ->reloadPage();
    }
}
