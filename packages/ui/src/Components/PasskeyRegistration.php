<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Components;

use Lattice\Lattice\Ui\Components\Component;

class PasskeyRegistration extends Component
{
    public string $optionsUrl;

    public string $submitUrl;

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
