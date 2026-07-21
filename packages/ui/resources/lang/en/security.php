<?php

declare(strict_types=1);

return [
    'resend-verification' => 'Resend verification email',
    'verification-sent' => 'A new verification link has been sent to your email address.',
    'already-verified' => 'Your email address is already verified.',

    'two-factor' => [
        'enable' => 'Enable 2FA',
        'disable' => 'Disable 2FA',
        'disable-confirm-title' => 'Disable two-factor authentication?',
        'disable-confirm-description' => 'Your account will no longer require a one-time code during sign in.',
        'setup-started' => 'Two-factor authentication setup started.',
        'setup-key' => 'Or enter this setup key in your authenticator app:',
        'already-enabled' => 'Two-factor authentication is enabled. You can close this dialog.',
        'confirm' => 'Confirm',
        'code' => 'Authentication code',
        'code-help' => 'Enter the code from your authenticator application.',
        'enabled-toast' => 'Two-factor authentication enabled.',
        'disabled-toast' => 'Two-factor authentication disabled.',
        'invalid-code' => 'The provided two factor authentication code was invalid.',
    ],

    'recovery-codes' => [
        'regenerate' => 'Regenerate codes',
        'regenerate-confirm-title' => 'Regenerate recovery codes?',
        'regenerate-confirm-description' => 'Your existing recovery codes will stop working and be replaced with a new set.',
        'regenerated' => 'Recovery codes regenerated.',
    ],

    'passkeys' => [
        'column' => 'Passkey',
        'last-used' => 'Last used',
        'added' => 'Added :time',
        'never-used' => 'Never used',
        'last-used-at' => 'Last used :time',
        'remove' => 'Remove passkey',
        'remove-confirm-title' => 'Remove passkey?',
        'remove-confirm-description' => 'You will no longer be able to use this passkey to sign in.',
        'removed' => 'Passkey removed.',
    ],
];
