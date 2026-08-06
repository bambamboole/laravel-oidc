<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Components;

use Illuminate\Support\Facades\Route;
use Lattice\Ui\Components\Component;

/**
 * Drives the generic webauthn enrollment ceremony: begin returns the WebAuthn
 * creation options, confirm stores the attested passkey.
 */
class PasskeyRegistration extends Component
{
    public string $beginUrl;

    public string $confirmUrl;

    /**
     * The enrollment handlers can be disabled via oidc.handlers, in which case
     * make() cannot resolve the ceremony routes — check before composing the
     * component into a page.
     */
    public static function isAvailable(): bool
    {
        return Route::has('identity.two-factor.enroll') && Route::has('identity.two-factor.enroll.confirm');
    }

    public static function make(): static
    {
        $component = new static;
        $component->beginUrl = route('identity.two-factor.enroll', ['provider' => 'webauthn'], absolute: false);
        $component->confirmUrl = route('identity.two-factor.enroll.confirm', ['provider' => 'webauthn'], absolute: false);

        return $component;
    }

    protected function type(): string
    {
        return 'oidc.passkey-registration';
    }
}
