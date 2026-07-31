<?php

declare(strict_types=1);

return [
    'resend-verification' => 'Bestätigungs-E-Mail erneut senden',
    'verification-sent' => 'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.',
    'already-verified' => 'Deine E-Mail-Adresse ist bereits bestätigt.',

    'two-factor' => [
        'enable' => ':method hinzufügen',
        'disable' => 'Zwei-Faktor-Authentifizierung deaktivieren',
        'disable-confirm-title' => 'Zwei-Faktor-Authentifizierung deaktivieren?',
        'disable-confirm-description' => 'Alle Zwei-Faktor-Methoden außer Passkeys werden entfernt; die Anmeldung erfordert keinen zweiten Schritt mehr.',
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

    'methods' => [
        'column' => 'Methode',
        'last-used' => 'Zuletzt verwendet',
        'never-used' => 'Nie verwendet',
        'last-used-at' => 'Zuletzt verwendet :time',
        'remove' => 'Methode entfernen',
        'remove-confirm-title' => 'Zwei-Faktor-Methode entfernen?',
        'remove-confirm-description' => 'Du kannst diese Methode dann nicht mehr bei der Anmeldung verwenden.',
        'removed' => 'Zwei-Faktor-Methode entfernt.',
    ],

    'recovery-codes' => [
        'regenerate' => 'Codes neu generieren',
        'regenerate-confirm-title' => 'Wiederherstellungscodes neu generieren?',
        'regenerate-confirm-description' => 'Deine bestehenden Wiederherstellungscodes werden ungültig und durch einen neuen Satz ersetzt.',
        'regenerated' => 'Wiederherstellungscodes neu generiert.',
        'description' => 'Bewahre diese Wiederherstellungscodes an einem sicheren Ort auf. Jeder Code kann einmal verwendet werden, falls du den Zugriff auf deine anderen Methoden verlierst.',
        'none' => 'Keine Wiederherstellungscodes vorhanden. Sie werden generiert, sobald du deine erste Zwei-Faktor-Methode bestätigst.',
    ],
];
