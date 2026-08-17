<?php
declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Ui\Support;

use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Data\EnrollmentOption;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorRole;
use Bambamboole\LaravelOidc\Server\Auth\MultiFactor\Enums\FactorSetupKind;

/**
 * Presentation for an {@see EnrollmentOption}. The server package describes what
 * an option *is*; the wording and iconography live here, so a host-registered
 * provider renders with its own translations under
 * `oidc-ui::security.option.{id}.*` and still reads sensibly without them.
 */
final class EnrollmentOptionLabels
{
    public static function label(EnrollmentOption $option): string
    {
        return self::translate($option, 'label') ?? $option->id;
    }

    public static function description(EnrollmentOption $option): string
    {
        return self::translate($option, 'description') ?? '';
    }

    /**
     * Whether the option also replaces the password at sign-in, phrased for a
     * badge rather than as an enum name.
     */
    public static function role(EnrollmentOption $option): string
    {
        return $option->role === FactorRole::LoginAndSecondFactor
            ? __('oidc-ui::security.role.login-and-second-factor')
            : __('oidc-ui::security.role.second-factor-only');
    }

    /**
     * A sprite symbol name, not a Lattice `Icon` case — that enum is a small
     * curated set and carries none of these. Hosts feed their sprite from their
     * own icon directory, so an unknown name degrades to an empty glyph rather
     * than an error.
     */
    public static function icon(EnrollmentOption $option): string
    {
        return match ($option->id) {
            'passkey' => 'fingerprint',
            'security_key' => 'usb',
            'totp' => 'smartphone',
            default => $option->setupKind === FactorSetupKind::Ceremony ? 'key-round' : 'shield-check',
        };
    }

    private static function translate(EnrollmentOption $option, string $suffix): ?string
    {
        $key = "oidc-ui::security.option.{$option->id}.{$suffix}";

        return trans()->has($key) ? __($key) : null;
    }
}
