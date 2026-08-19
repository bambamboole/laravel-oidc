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
use Bambamboole\LaravelOidc\Ui\Support\RecoveryCodesModal;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lattice\Core\Enums\ColorName;
use Lattice\Core\Option;
use Lattice\Facades\Effects;
use Lattice\Form\Attributes\AsForm;
use Lattice\Form\Components\Choice;
use Lattice\Form\Components\Form;
use Lattice\Form\Components\Wizard;
use Lattice\Form\Components\WizardStep;
use Lattice\Form\FormDefinition;
use Lattice\Http\LatticeResponse;
use Lattice\Ui\Components\Badge;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\Icon;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\Enums\Size;
use Lattice\Ui\Enums\StackDirection;
use Lattice\Ui\Enums\Variant;
use Lattice\Ui\Enums\Width;

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
                            ->optionSchema($this->pickerCard())
                            // The registry sorts the recommended option first and
                            // the picker shows it checked. Saying so on the wire is
                            // what makes step two resolve for a user who accepts
                            // the recommendation instead of clicking a card.
                            ->value($options[0]->id ?? null)
                            ->rules(['required', Rule::in(array_column($options, 'id'))]),
                    ]),
                WizardStep::make('configure', __('oidc-ui::security.setup.step-configure'))
                    ->description(__('oidc-ui::security.setup.step-configure-description'))
                    ->schema([
                        TwoFactorSetupField::make('setup', __('oidc-ui::security.setup.confirmation')),
                    ]),
            ])->align(Align::Center),
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
            $response = $response->openModal(RecoveryCodesModal::make(
                (string) $this->context('recovery_codes_modal', RecoveryCodesModal::DEFAULT_ID),
            ));
        }

        return $response->back();
    }

    /**
     * One card per option: icon, name, what it is good for, and a sentence of
     * plain language. The schema ships once — the options carry only data.
     *
     * There is deliberately no "recommended" badge: a component's visibility is
     * decided when the schema is built, not per option, so a badge bound to a
     * boolean would render an empty pill for every other choice. The
     * recommendation is carried by order instead — the registry sorts the
     * recommended option first, and a Choice preselects its first option.
     *
     * @return array<int, Component>
     */
    private function pickerCard(): array
    {
        return [
            Stack::make()
                ->direction(StackDirection::Row)
                ->align(Align::Center)
                ->gap(Gap::Medium)
                ->schema([
                    Icon::make('')->dataKey('name', 'icon')->size(Size::Lg),
                    // A row is a wrapping flex box and a stack is full-width by
                    // default, so the text column has to claim the remaining space
                    // instead — otherwise it wraps under the icon.
                    Stack::make()
                        ->width(Width::Fill)
                        ->gap(Gap::Small)
                        ->schema([
                            Stack::make()
                                ->direction(StackDirection::Row)
                                ->align(Align::Center)
                                ->gap(Gap::Small)
                                ->schema([
                                    Text::make('')->dataKey('text', 'label'),
                                    Badge::make('')->dataKey('label', 'role'),
                                ]),
                            Text::make('')
                                ->dataKey('text', 'description')
                                ->size(Size::Sm)
                                ->color(ColorName::Muted),
                        ]),
                ]),
        ];
    }

    /**
     * The option's data is what the card schema binds against; a plain pill
     * renderer ignores everything but the label.
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
     * the label the user typed while it was in flight. The credential travels as
     * a JSON string in a hidden input, because Inertia serializes the DOM on
     * submit and a nested object cannot be mounted as one.
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
        $credential = $submitted['credential'] ?? null;

        if (is_string($credential)) {
            $credential = json_decode($credential, true);
        }

        return [
            'credential' => is_array($credential) ? $credential : [],
            'name' => is_string($name) ? $name : null,
        ];
    }
}
