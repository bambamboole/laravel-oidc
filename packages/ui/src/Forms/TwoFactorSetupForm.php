<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Forms;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Data\EnrollmentOption;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\EnrollmentPolicy;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorSetupKind;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Ui\Concerns\ManagesTwoFactor;
use Bambamboole\LaravelOidc\Ui\Fields\TwoFactorSetupField;
use Bambamboole\LaravelOidc\Ui\Support\EnrollmentOptionLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lattice\Core\Option;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Choice;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\Wizard;
use Lattice\Form\Components\WizardStep;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Enums\Variant;

/**
 * Adding a second factor: pick a method, then configure it.
 *
 * One form for every provider. Step one is built from
 * {@see FactorRegistry::enrollmentOptions()}, so a host-registered provider shows
 * up without touching this class; step two is a single field whose body the
 * chosen provider decides. The wizard's own Next button validates step one
 * through Precognition, which is what triggers the resolve that prepares step
 * two — by the time the user gets there, the enrollment has begun.
 */
#[AsForm('oidc.two-factor.setup')]
class TwoFactorSetupForm extends FormDefinition
{
    use ManagesTwoFactor;

    public function __construct(
        private readonly FactorRegistry $factors,
        private readonly EnrollmentPolicy $policy,
    ) {}

    public function definition(Form $form, Request $request): Form
    {
        $options = $this->factors->enrollmentOptions();

        return $form->schema([
            Wizard::make([
                WizardStep::make('method', __('oidc-ui::security.setup.step-method'))
                    ->description(__('oidc-ui::security.setup.step-method-description'))
                    ->schema([
                        Choice::make('option', __('oidc-ui::security.setup.method'))
                            ->options(array_map($this->pickerOption(...), $options))
                            ->rules(['required', Rule::in(array_column($options, 'id'))]),
                    ]),
                WizardStep::make('configure', __('oidc-ui::security.setup.step-configure'))
                    ->description(__('oidc-ui::security.setup.step-configure-description'))
                    ->schema([
                        TwoFactorSetupField::make('setup', __('oidc-ui::security.setup.confirmation')),
                    ]),
            ]),
        ]);
    }

    public function handle(Request $request): LatticeResponse
    {
        $user = $this->twoFactorUser();
        $option = $this->factors->enrollmentOption((string) $request->input('option')) ?? abort(404);
        $provider = $this->factors->enrollable($option->providerKey) ?? abort(404);

        $pending = Arr::last(
            $provider->enrollments($user),
            static fn (FactorEnrollment $enrollment): bool => $enrollment->confirmedAt === null,
        );

        $confirmed = $pending instanceof FactorEnrollment && $provider->confirmEnrollment(
            $user,
            $pending,
            $this->confirmationInput($option, $request->input('setup')),
        );

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'setup' => __('oidc-ui::security.setup.invalid'),
            ]);
        }

        $response = Effects::respond()->toast(
            __('oidc-ui::security.setup.confirmed', ['method' => EnrollmentOptionLabels::label($option)]),
            Variant::Success,
        );

        // Freshly generated recovery codes are shown once — right after the first
        // factor of any kind is confirmed.
        if ($this->policy->factorConfirmed($user)) {
            $response = $response->openModal((string) $this->context('recovery_codes_modal', 'oidc.recovery-codes'));
        }

        return $response->back();
    }

    /**
     * The option's data rides along for a card-style picker to bind against; a
     * plain pill renderer ignores everything but the label.
     */
    private function pickerOption(EnrollmentOption $option): Option
    {
        return Choice::option(
            EnrollmentOptionLabels::label($option),
            $option->id,
            [
                'description' => EnrollmentOptionLabels::description($option),
                'role' => EnrollmentOptionLabels::role($option),
                'icon' => EnrollmentOptionLabels::icon($option),
                'recommended' => $option->recommended,
            ],
        );
    }

    /**
     * A ceremony submits both halves — the attestation the browser produced and
     * the label the user typed while it was in flight.
     *
     * @return array<string, mixed>
     */
    private function confirmationInput(EnrollmentOption $option, mixed $value): array
    {
        if ($option->setupKind === FactorSetupKind::Code) {
            return ['code' => is_string($value) ? $value : ''];
        }

        $submitted = is_array($value) ? $value : [];
        $name = $submitted['name'] ?? null;

        return [
            'credential' => is_array($submitted['credential'] ?? null) ? $submitted['credential'] : [],
            'name' => is_string($name) ? $name : null,
        ];
    }
}
