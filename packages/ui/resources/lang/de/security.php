<?php

declare(strict_types=1);

return [
    'resend-verification' => 'Bestätigungs-E-Mail erneut senden',
    'verification-sent' => 'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.',
    'already-verified' => 'Deine E-Mail-Adresse ist bereits bestätigt.',

    'two-factor' => [
        'enable' => '2FA aktivieren',
        'disable' => '2FA deaktivieren',
        'disable-confirm-title' => 'Zwei-Faktor-Authentifizierung deaktivieren?',
        'disable-confirm-description' => 'Dein Konto erfordert bei der Anmeldung keinen Einmalcode mehr.',
        'setup-started' => 'Einrichtung der Zwei-Faktor-Authentifizierung gestartet.',
        'setup-key' => 'Oder gib diesen Einrichtungsschlüssel in deiner Authenticator-App ein:',
        'already-enabled' => 'Die Zwei-Faktor-Authentifizierung ist aktiviert. Du kannst diesen Dialog schließen.',
        'confirm' => 'Bestätigen',
        'code' => 'Authentifizierungscode',
        'code-help' => 'Gib den Code aus deiner Authenticator-App ein.',
        'enabled-toast' => 'Zwei-Faktor-Authentifizierung aktiviert.',
        'disabled-toast' => 'Zwei-Faktor-Authentifizierung deaktiviert.',
        'invalid-code' => 'Der eingegebene Zwei-Faktor-Authentifizierungscode war ungültig.',
    ],

    'recovery-codes' => [
        'regenerate' => 'Codes neu generieren',
        'regenerate-confirm-title' => 'Wiederherstellungscodes neu generieren?',
        'regenerate-confirm-description' => 'Deine bestehenden Wiederherstellungscodes werden ungültig und durch einen neuen Satz ersetzt.',
        'regenerated' => 'Wiederherstellungscodes neu generiert.',
    ],

    'passkeys' => [
        'column' => 'Passkey',
        'last-used' => 'Zuletzt verwendet',
        'added' => 'Hinzugefügt :time',
        'never-used' => 'Nie verwendet',
        'last-used-at' => 'Zuletzt verwendet :time',
        'remove' => 'Passkey entfernen',
        'remove-confirm-title' => 'Passkey entfernen?',
        'remove-confirm-description' => 'Du kannst diesen Passkey dann nicht mehr zur Anmeldung verwenden.',
        'removed' => 'Passkey entfernt.',
    ],
];
