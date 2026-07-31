<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Actions;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\EnrollmentPolicy;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Lattice\Lattice\Actions\ActionDefinition;
use Lattice\Lattice\Actions\ActionResult;
use Lattice\Lattice\Actions\Components\Action;
use Lattice\Lattice\Attributes\AsAction;
use Lattice\Lattice\Ui\Enums\Emphasis;
use Lattice\Lattice\Ui\Enums\Variant;

#[AsAction('oidc.two-factor.revoke-factor')]
class RevokeFactorAction extends ActionDefinition
{
    use ManagesTwoFactor;

    public function __construct(
        private readonly FactorRegistry $factors,
        private readonly EnrollmentPolicy $policy,
    ) {}

    public function definition(Action $action): Action
    {
        return $action
            ->label(__('oidc-ui::security.methods.remove'))
            ->emphasis(Emphasis::Ghost)
            ->confirm(
                title: __('oidc-ui::security.methods.remove-confirm-title'),
                description: __('oidc-ui::security.methods.remove-confirm-description'),
                confirmLabel: __('oidc-ui::security.methods.remove'),
            );
    }

    #[\Override]
    public function authorize(Request $request): bool
    {
        return $this->contextEnrollment() instanceof FactorEnrollment;
    }

    public function handle(Request $request): ActionResult
    {
        $user = $this->twoFactorUser();
        $enrollment = $this->contextEnrollment() ?? abort(404);

        ($this->factors->enrollable($this->providerKey()) ?? abort(404))->revoke($user, $enrollment);
        $this->policy->factorRevoked($user);

        return ActionResult::success()
            ->toast(__('oidc-ui::security.methods.removed'), Variant::Success)
            ->reloadComponent('oidc.two-factor.methods');
    }

    private function contextEnrollment(): ?FactorEnrollment
    {
        return Arr::first(
            $this->factors->enrollable($this->providerKey())?->enrollments($this->twoFactorUser()) ?? [],
            fn (FactorEnrollment $enrollment): bool => $enrollment->id === (string) $this->context('enrollment'),
        );
    }
}
