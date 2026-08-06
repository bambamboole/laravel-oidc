<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Forms;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\EnrollmentPolicy;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\OtpInput;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Effects\Builtin\OpenModal;
use Lattice\Ui\Enums\Variant;

#[AsForm('oidc.two-factor.confirm')]
class ConfirmTwoFactorForm extends FormDefinition
{
    use ManagesTwoFactor;

    public function __construct(
        private readonly FactorRegistry $factors,
        private readonly EnrollmentPolicy $policy,
    ) {}

    public function definition(Form $form, Request $request): Form
    {
        return $form
            ->schema([
                OtpInput::make('code', __('oidc-ui::security.two-factor.code'))
                    ->length(6)
                    ->helperText(__('oidc-ui::security.two-factor.code-help'))
                    ->rules(['required', 'string']),
            ])
            ->submitLabel(__('oidc-ui::security.two-factor.confirm'));
    }

    public function handle(Request $request): LatticeResponse
    {
        $user = $this->twoFactorUser();
        $enrollable = $this->factors->enrollable($this->providerKey()) ?? abort(404);

        $pending = Arr::last($enrollable->enrollments($user), fn (FactorEnrollment $enrollment): bool => $enrollment->confirmedAt === null);

        $confirmed = $pending instanceof FactorEnrollment && $enrollable->confirmEnrollment(
            $user,
            $pending,
            ['code' => (string) $request->input('code')],
        );

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'code' => __('oidc-ui::security.two-factor.invalid-code'),
            ]);
        }

        $backfilledCodes = $this->policy->factorConfirmed($user);

        $response = Effects::respond()->toast(__('oidc-ui::security.two-factor.enabled-toast'), Variant::Success);

        // Freshly generated recovery codes are shown once — right after the
        // first factor is confirmed.
        if ($backfilledCodes) {
            $response = $response->effect(new OpenModal((string) $this->context('recovery_codes_modal', 'oidc.recovery-codes')));
        }

        return $response->back();
    }
}
