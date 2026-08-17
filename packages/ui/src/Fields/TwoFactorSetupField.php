<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Fields;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Data\EnrollmentOption;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorSetupKind;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\FactorRegistry;
use Lattice\Form\Attributes\AsField;
use Lattice\Form\Components\Field;

/**
 * The second step of the two-factor setup wizard: whatever the chosen provider
 * needs the user to do, plus the value that proves they did it.
 *
 * The setup payload cannot be known when the form is built — it only exists once
 * an enrollment has begun, and which enrollment depends on the option picked in
 * step one. So the field is *computed* on `option`: changing that choice fires
 * Lattice's resolve sub-request, which begins the enrollment server-side and
 * hands the fresh payload back as this field's props.
 *
 * Beginning an enrollment from a resolve is a deliberate write on a read-shaped
 * call. It is safe because it is idempotent per option: the TOTP provider reuses
 * an existing unconfirmed factor, and webauthn overwrites a single session slot.
 */
#[AsField('oidc.two-factor-setup')]
class TwoFactorSetupField extends Field
{
    /** `code` or `ceremony` — which body the client renders. */
    public ?string $kind = null;

    public ?string $qrSvg = null;

    public ?string $secret = null;

    public int $otpLength = 6;

    /** @var array<string, mixed>|null */
    public ?array $webauthnOptions = null;

    /** The enrollment the user is confirming, echoed back on submit. */
    public ?string $enrollmentId = null;

    /**
     * The dependency on `option` is the field's whole reason for existing, so it
     * is wired here rather than left to every caller to remember.
     */
    #[\Override]
    public static function make(string $name, ?string $label = null): static
    {
        return parent::make($name, $label)->dependsOn('option', self::resolveSetup(...));
    }

    /**
     * Validation follows the resolved kind, so the wizard's Finish button is
     * gated on the step actually being completed rather than merely visited.
     *
     * @return array<int, mixed>
     */
    protected function defaultRules(): array
    {
        return match ($this->kind) {
            FactorSetupKind::Code->value => ['required', 'string'],
            FactorSetupKind::Ceremony->value => ['required', 'array'],
            default => ['required'],
        };
    }

    /**
     * Writes to `$component`, never `$this`: the schema walker resolves against a
     * clone of the field, while the closure built in make() stays bound to the
     * original. Mutating `$this` would update an object nobody serializes — the
     * enrollment would be created and the payload silently lost.
     *
     * Validation resolves every field before collecting rules, so this also runs
     * on submit. `kind` is derived from the option either way, because the rules
     * need it, but the enrollment is only begun while the field is still empty.
     * Re-beginning at submit time would hand webauthn a fresh challenge and
     * invalidate the credential the browser had just produced against the old one.
     */
    private static function resolveSetup(self $component, callable $get, mixed $value): void
    {
        $component->reset();

        $option = self::option((string) ($get('option') ?? ''));
        $user = auth()->user();

        if (! $option instanceof EnrollmentOption || $user === null) {
            return;
        }

        $component->kind = $option->setupKind->value;

        if (self::isFilled($value)) {
            return;
        }

        $enrollment = (app(FactorRegistry::class)->enrollable($option->providerKey) ?? abort(404))
            ->beginEnrollment($user, $option);

        $component->enrollmentId = $enrollment->id;

        $metadata = $enrollment->metadata;
        $component->qrSvg = is_string($metadata['qr_svg'] ?? null) ? $metadata['qr_svg'] : null;
        $component->secret = is_string($metadata['secret'] ?? null) ? $metadata['secret'] : null;
        $component->webauthnOptions = is_array($metadata['options'] ?? null) ? $metadata['options'] : null;
    }

    private static function isFilled(mixed $value): bool
    {
        return match (true) {
            is_string($value) => $value !== '',
            is_array($value) => $value !== [],
            default => $value !== null,
        };
    }

    private static function option(string $id): ?EnrollmentOption
    {
        return $id === '' ? null : app(FactorRegistry::class)->enrollmentOption($id);
    }

    /**
     * A re-resolution must not leave the previous option's payload behind — a
     * stale QR code for a factor the user has moved away from is worse than none.
     */
    private function reset(): void
    {
        $this->kind = null;
        $this->qrSvg = null;
        $this->secret = null;
        $this->webauthnOptions = null;
        $this->enrollmentId = null;
    }
}
