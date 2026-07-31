<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Support;

final class FactorMethodName
{
    /**
     * The translated display name for a factor provider key, falling back to
     * the raw key so host-registered providers render without package
     * translations.
     */
    public static function for(string $providerKey): string
    {
        $labelKey = "oidc-ui::auth.two-factor.method.{$providerKey}";

        return trans()->has($labelKey) ? __($labelKey) : $providerKey;
    }
}
