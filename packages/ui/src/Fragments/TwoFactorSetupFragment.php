<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Fragments;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Contracts\EnrollableFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorEnrollment;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\WebAuthnFactorProvider;
use Bambamboole\LaravelOidc\Ui\Components\PasskeyRegistration;
use Bambamboole\LaravelOidc\Ui\Forms\ConfirmTwoFactorForm;
use Illuminate\Support\Arr;
use Lattice\Core\Attributes\AsFragment;
use Lattice\Form\Components\Form;
use Lattice\Fragments\FragmentDefinition;
use Lattice\Ui\Components\Component;
use Lattice\Ui\Components\RawBlock;
use Lattice\Ui\Components\Stack;
use Lattice\Ui\Components\Text;
use Lattice\Ui\Enums\Align;
use Lattice\Ui\Enums\Gap;
use Lattice\Ui\PageSchema;

#[AsFragment('oidc.two-factor-setup')]
class TwoFactorSetupFragment extends FragmentDefinition
{
    public function __construct(private readonly FactorRegistry $factors) {}

    public function schema(PageSchema $schema): PageSchema
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        $provider = $this->factors->enrollable((string) $this->context('provider', 'totp')) ?? abort(404);

        if ($provider instanceof TotpFactorProvider) {
            $factor = $provider->latestFactor($user);

            if ($factor === null || $factor->confirmed_at !== null) {
                return $schema->schema([
                    Text::make(__('oidc-ui::security.two-factor.already-enabled')),
                ]);
            }

            return $schema->schema([
                $this->setupStack([
                    RawBlock::make('two-factor-qr-code')->html($provider->qrCodeSvg($factor, $user)),
                    Text::make(__('oidc-ui::security.two-factor.setup-key')),
                    Text::make($factor->secret),
                ], $provider),
            ]);
        }

        if ($provider instanceof WebAuthnFactorProvider) {
            abort_unless(PasskeyRegistration::isAvailable(), 404);

            return $schema->schema([
                Stack::make('two-factor-setup')
                    ->align(Align::Center)
                    ->gap(Gap::Medium)
                    ->schema([PasskeyRegistration::make()]),
            ]);
        }

        $pending = Arr::last($provider->enrollments($user), fn (FactorEnrollment $enrollment): bool => $enrollment->confirmedAt === null);

        if (! $pending instanceof FactorEnrollment) {
            return $schema->schema([
                Text::make(__('oidc-ui::security.two-factor.already-enabled')),
            ]);
        }

        // A custom provider's setup payload lives in the pending enrollment's
        // metadata; scalar values (a secret, a code) are rendered as-is, and
        // richer setup UI stays a host-side fragment override.
        $metadata = [];
        foreach ($pending->metadata as $value) {
            if (is_scalar($value)) {
                $metadata[] = Text::make((string) $value);
            }
        }

        return $schema->schema([
            $this->setupStack([Text::make($pending->label), ...$metadata], $provider),
        ]);
    }

    /**
     * @param  list<Component>  $components
     */
    private function setupStack(array $components, EnrollableFactorProvider $provider): Stack
    {
        return Stack::make('two-factor-setup')
            ->align(Align::Center)
            ->gap(Gap::Medium)
            ->schema([
                ...$components,
                Form::use(ConfirmTwoFactorForm::class, ['provider' => $provider->key()]),
            ]);
    }
}
