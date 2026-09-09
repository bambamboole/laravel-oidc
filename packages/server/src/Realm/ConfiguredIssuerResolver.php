<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Realm;

final class ConfiguredIssuerResolver implements IssuerResolver
{
    public function url(): string
    {
        return rtrim((string) (config('oidc.issuer') ?: config('app.url')), '/');
    }
}
