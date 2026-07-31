<?php

declare(strict_types=1);

return [
    'resend-verification' => 'Resend verification email',
    'verification-sent' => 'A new verification link has been sent to your email address.',
    'already-verified' => 'Your email address is already verified.',

    'two-factor' => [
        'enable' => 'Add :method',
        'disable' => 'Disable two-factor authentication',
        'disable-confirm-title' => 'Disable two-factor authentication?',
        'disable-confirm-description' => 'All two-factor methods except passkeys will be removed, and sign-in will no longer require a second step.',
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

    'methods' => [
        'column' => 'Method',
        'last-used' => 'Last used',
        'never-used' => 'Never used',
        'last-used-at' => 'Last used :time',
        'remove' => 'Remove method',
        'remove-confirm-title' => 'Remove two-factor method?',
        'remove-confirm-description' => 'You will no longer be able to use this method during sign in.',
        'removed' => 'Two-factor method removed.',
    ],

    'recovery-codes' => [
        'regenerate' => 'Regenerate codes',
        'regenerate-confirm-title' => 'Regenerate recovery codes?',
        'regenerate-confirm-description' => 'Your existing recovery codes will stop working and be replaced with a new set.',
        'regenerated' => 'Recovery codes regenerated.',
        'description' => 'Store these recovery codes in a safe place. Each code can be used once to sign in if you lose access to your other methods.',
        'none' => 'There are no recovery codes to show. They are generated when you confirm your first two-factor method.',
    ],
];
