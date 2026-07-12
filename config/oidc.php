<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Controllers\AuthenticatedSessionController;
use Bambamboole\LaravelOidc\Auth\Controllers\ConfirmablePasswordController;
use Bambamboole\LaravelOidc\Auth\Controllers\ConfirmTwoFactorAuthenticationController;
use Bambamboole\LaravelOidc\Auth\Controllers\DisableTwoFactorAuthenticationController;
use Bambamboole\LaravelOidc\Auth\Controllers\EmailVerificationPromptController;
use Bambamboole\LaravelOidc\Auth\Controllers\EnableTwoFactorAuthenticationController;
use Bambamboole\LaravelOidc\Auth\Controllers\NewPasswordController;
use Bambamboole\LaravelOidc\Auth\Controllers\PasswordResetLinkController;
use Bambamboole\LaravelOidc\Auth\Controllers\RegenerateRecoveryCodesController;
use Bambamboole\LaravelOidc\Auth\Controllers\RegisteredUserController;
use Bambamboole\LaravelOidc\Auth\Controllers\SendEmailVerificationNotificationController;
use Bambamboole\LaravelOidc\Auth\Controllers\ShowConfirmedPasswordStatusController;
use Bambamboole\LaravelOidc\Auth\Controllers\ShowRecoveryCodesController;
use Bambamboole\LaravelOidc\Auth\Controllers\ShowTwoFactorQrCodeController;
use Bambamboole\LaravelOidc\Auth\Controllers\ShowTwoFactorSecretKeyController;
use Bambamboole\LaravelOidc\Auth\Controllers\TwoFactorChallengeController;
use Bambamboole\LaravelOidc\Auth\Controllers\VerifyEmailController;
use Bambamboole\LaravelOidc\Auth\Middleware\AuthenticateIdentity;
use Bambamboole\LaravelOidc\Auth\MultiFactor\RecoveryCodeProvider;
use Bambamboole\LaravelOidc\Auth\MultiFactor\TotpFactorProvider;
use Bambamboole\LaravelOidc\Auth\MultiFactor\WebAuthnFactorProvider;
use Bambamboole\LaravelOidc\Http\Controllers\ApproveAuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\AuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\DenyAuthorizationController;
use Bambamboole\LaravelOidc\Http\Controllers\DiscoveryController;
use Bambamboole\LaravelOidc\Http\Controllers\EndSessionController;
use Bambamboole\LaravelOidc\Http\Controllers\IntrospectionController;
use Bambamboole\LaravelOidc\Http\Controllers\JwksController;
use Bambamboole\LaravelOidc\Http\Controllers\RevocationController;
use Bambamboole\LaravelOidc\Http\Controllers\UserinfoController;
use Bambamboole\LaravelOidc\Routing\Handler;
use Illuminate\Auth\Middleware\RequirePassword;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\TransientTokenController;

return [
    'issuer' => env('OIDC_ISSUER'),

    'id_token_ttl' => (int) env('OIDC_ID_TOKEN_TTL', 3600),

    'api_guard' => env('OIDC_API_GUARD', 'api'),

    'claims_supported' => [
        'iss', 'sub', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'at_hash', 'azp',
        'name', 'email', 'email_verified', 'locale', 'zoneinfo', 'updated_at',
    ],

    'token_exchange' => [
        'enabled' => env('OIDC_TOKEN_EXCHANGE_ENABLED', true),
    ],

    'key_size' => (int) env('OIDC_KEY_SIZE', 2048),

    'additional_public_keys' => array_values(array_filter([
        str_replace('\n', "\n", (string) env('OIDC_PREVIOUS_PUBLIC_KEY', '')),
    ], static fn (string $pem): bool => trim($pem) !== '')),

    'logout_redirect' => '/',

    'first_party' => [
        'client_id' => env('OIDC_FIRST_PARTY_CLIENT') ?: null,
        'trusted' => env('OIDC_FIRST_PARTY_TRUSTED', false),
    ],

    'trusted_clients' => [],

    'login_route' => env('OIDC_LOGIN_ROUTE', 'login'),

    'session_token' => [
        'ttl' => (int) env('OIDC_SESSION_TOKEN_TTL', 3600),
        'session_key' => 'oidc.session_token',
        'refresh_skew' => 60,
        'scopes' => null,
    ],

    'auth' => [
        'guard' => env('OIDC_AUTH_GUARD', 'identity'),
        'provider' => env('OIDC_AUTH_PROVIDER', 'users'),
        'home' => env('OIDC_AUTH_HOME', '/dashboard'),
        'username' => env('OIDC_AUTH_USERNAME', 'email'),
        'two_factor' => [
            'challenge_providers' => ['totp'],
            'secret_length' => 16,
            'window' => 1,
            'recovery_codes' => 8,
        ],
        'factors' => [
            TotpFactorProvider::class,
            RecoveryCodeProvider::class,
            WebAuthnFactorProvider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route handlers
    |--------------------------------------------------------------------------
    |
    | Every HTTP endpoint the package registers is defined here, keyed by its
    | route name. Each entry has a `route` (URI path), a `controller`, and a
    | `middleware` list. Customize any of these, or set an entry to `false` to
    | disable that endpoint entirely. The HTTP verb is intrinsic to each
    | endpoint and is therefore not configurable here.
    |
    */
    'handlers' => [
        Handler::Login->value => [
            'route' => 'auth/login',
            'controller' => [AuthenticatedSessionController::class, 'create'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::LoginStore->value => [
            'route' => 'auth/login',
            'controller' => [AuthenticatedSessionController::class, 'store'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:5,1'],
        ],
        Handler::Register->value => [
            'route' => 'auth/register',
            'controller' => [RegisteredUserController::class, 'create'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::RegisterStore->value => [
            'route' => 'auth/register',
            'controller' => [RegisteredUserController::class, 'store'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordRequest->value => [
            'route' => 'auth/forgot-password',
            'controller' => [PasswordResetLinkController::class, 'create'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordEmail->value => [
            'route' => 'auth/forgot-password',
            'controller' => [PasswordResetLinkController::class, 'store'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordReset->value => [
            'route' => 'auth/reset-password/{token}',
            'controller' => [NewPasswordController::class, 'create'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordUpdate->value => [
            'route' => 'auth/reset-password',
            'controller' => [NewPasswordController::class, 'store'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordConfirm->value => [
            'route' => 'auth/user/confirm-password',
            'controller' => [ConfirmablePasswordController::class, 'show'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordConfirmStore->value => [
            'route' => 'auth/user/confirm-password',
            'controller' => [ConfirmablePasswordController::class, 'store'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::PasswordConfirmation->value => [
            'route' => 'auth/user/confirmed-password-status',
            'controller' => ShowConfirmedPasswordStatusController::class,
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::VerificationNotice->value => [
            'route' => 'auth/email/verify',
            'controller' => EmailVerificationPromptController::class,
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::VerificationVerify->value => [
            'route' => 'auth/email/verify/{id}/{hash}',
            'controller' => VerifyEmailController::class,
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'), 'signed', 'throttle:6,1'],
        ],
        Handler::VerificationSend->value => [
            'route' => 'auth/email/verification-notification',
            'controller' => SendEmailVerificationNotificationController::class,
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:6,1'],
        ],
        Handler::TwoFactorLogin->value => [
            'route' => 'auth/two-factor-challenge',
            'controller' => [TwoFactorChallengeController::class, 'create'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::TwoFactorLoginStore->value => [
            'route' => 'auth/two-factor-challenge',
            'controller' => [TwoFactorChallengeController::class, 'store'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:5,1'],
        ],
        Handler::TwoFactorEnable->value => [
            'route' => 'auth/user/two-factor-authentication',
            'controller' => EnableTwoFactorAuthenticationController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::TwoFactorConfirm->value => [
            'route' => 'auth/user/confirmed-two-factor-authentication',
            'controller' => ConfirmTwoFactorAuthenticationController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::TwoFactorDisable->value => [
            'route' => 'auth/user/two-factor-authentication',
            'controller' => DisableTwoFactorAuthenticationController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::TwoFactorQrCode->value => [
            'route' => 'auth/user/two-factor-qr-code',
            'controller' => ShowTwoFactorQrCodeController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::TwoFactorSecretKey->value => [
            'route' => 'auth/user/two-factor-secret-key',
            'controller' => ShowTwoFactorSecretKeyController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::TwoFactorRecoveryCodes->value => [
            'route' => 'auth/user/two-factor-recovery-codes',
            'controller' => ShowRecoveryCodesController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::TwoFactorRegenerateRecoveryCodes->value => [
            'route' => 'auth/user/two-factor-recovery-codes',
            'controller' => RegenerateRecoveryCodesController::class,
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],
        Handler::PasskeyLoginOptions->value => [
            'route' => 'auth/passkeys/login/options',
            'controller' => [PasskeyLoginController::class, 'index'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:5,1'],
        ],
        Handler::PasskeyLogin->value => [
            'route' => 'auth/passkeys/login',
            'controller' => [PasskeyLoginController::class, 'store'],
            'middleware' => ['web', 'guest:'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:5,1'],
        ],
        Handler::PasskeyConfirmOptions->value => [
            'route' => 'auth/passkeys/confirm/options',
            'controller' => [PasskeyConfirmationController::class, 'index'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:5,1'],
        ],
        Handler::PasskeyConfirm->value => [
            'route' => 'auth/passkeys/confirm',
            'controller' => [PasskeyConfirmationController::class, 'store'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'), 'throttle:5,1'],
        ],
        Handler::PasskeyRegistrationOptions->value => [
            'route' => 'auth/user/passkeys/options',
            'controller' => [PasskeyRegistrationController::class, 'index'],
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
                'throttle:5,1',
            ],
        ],
        Handler::PasskeyStore->value => [
            'route' => 'auth/user/passkeys',
            'controller' => [PasskeyRegistrationController::class, 'store'],
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
                'throttle:5,1',
            ],
        ],
        Handler::PasskeyDestroy->value => [
            'route' => 'auth/user/passkeys/{passkey}',
            'controller' => [PasskeyRegistrationController::class, 'destroy'],
            'middleware' => [
                'web',
                AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity'),
                RequirePassword::using(Handler::PasswordConfirm->value),
            ],
        ],

        Handler::Jwks->value => [
            'route' => '.well-known/jwks.json',
            'controller' => JwksController::class,
            'middleware' => [],
        ],
        Handler::Discovery->value => [
            'route' => '.well-known/openid-configuration',
            'controller' => DiscoveryController::class,
            'middleware' => [],
        ],
        Handler::Userinfo->value => [
            'route' => 'oauth/userinfo',
            'controller' => UserinfoController::class,
            'middleware' => [],
        ],
        Handler::Logout->value => [
            'route' => 'oauth/logout',
            'controller' => EndSessionController::class,
            'middleware' => ['web'],
        ],
        Handler::Introspect->value => [
            'route' => 'oauth/introspect',
            'controller' => IntrospectionController::class,
            'middleware' => ['throttle'],
        ],
        Handler::Revoke->value => [
            'route' => 'oauth/revoke',
            'controller' => RevocationController::class,
            'middleware' => ['throttle'],
        ],
        Handler::Authorize->value => [
            'route' => 'oauth/authorize',
            'controller' => [AuthorizationController::class, 'authorize'],
            'middleware' => ['web'],
        ],
        Handler::IssueToken->value => [
            'route' => 'oauth/token',
            'controller' => [AccessTokenController::class, 'issueToken'],
            'middleware' => ['throttle'],
        ],
        Handler::TokenRefresh->value => [
            'route' => 'oauth/token/refresh',
            'controller' => [TransientTokenController::class, 'refresh'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::Approve->value => [
            'route' => 'oauth/authorize',
            'controller' => [ApproveAuthorizationController::class, 'approve'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
        Handler::Deny->value => [
            'route' => 'oauth/authorize',
            'controller' => [DenyAuthorizationController::class, 'deny'],
            'middleware' => ['web', AuthenticateIdentity::class.':'.env('OIDC_AUTH_GUARD', 'identity')],
        ],
    ],
];
