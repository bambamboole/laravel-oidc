<?php

declare(strict_types=1);

it('registers the oidc-ui translations on the loader so its strings resolve', function (): void {
    // Asserted on the loader singleton, not only through the translator: Lattice's
    // i18next /locales/{lng}/{ns}.json route resolves `translation.loader` directly,
    // so a namespace registered via the deferred loadTranslationsFrom() callback
    // would be invisible to it.
    $hints = app('translation.loader')->namespaces();

    expect($hints)->toHaveKey('oidc-ui');
    expect(realpath($hints['oidc-ui']))->toBe(realpath(__DIR__.'/../resources/lang'));
    expect(app('translator')->hasForLocale('oidc-ui::auth.login.title', 'en'))->toBeTrue();
});
