<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Components;

use Illuminate\Support\Facades\Route;
use Lattice\Lattice\Ui\Components\Component;

class PasskeyRegistration extends Component
{
    public string $optionsUrl;

    public string $submitUrl;

    /**
     * The passkey handlers can be disabled via oidc.handlers, in which case
     * make() cannot resolve the ceremony routes — check before composing the
     * component into a page.
     */
    public static function isAvailable(): bool
    {
        return Route::has('identity.passkey.registration-options') && Route::has('identity.passkey.store');
    }

    public static function make(): static
    {
        $component = new static;
        $component->optionsUrl = route('identity.passkey.registration-options', absolute: false);
        $component->submitUrl = route('identity.passkey.store', absolute: false);

        return $component;
    }

    protected function type(): string
    {
        return 'oidc.passkey-registration';
    }
}
