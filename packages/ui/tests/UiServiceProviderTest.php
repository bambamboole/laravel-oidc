<?php

declare(strict_types=1);

it('boots and registers the oidc-ui translation namespace', function () {
    expect(app('translator')->hasForLocale('oidc-ui::auth.login.title', 'en'))->toBeTrue();
    expect(config('oidc-ui.brand_icon'))->toBe('logo');
});

it('registers the oidc-ui namespace directly on the translation loader', function () {
    // Namespaces are freshly resolvable from the loader singleton itself (not
    // only via the translator), because the i18next /locales/{lng}/{ns}.json
    // route resolves `translation.loader` directly and never touches the
    // translator, so a deferred loadTranslationsFrom() registration would be
    // invisible to it.
    $hints = app('translation.loader')->namespaces();

    expect($hints)->toHaveKey('oidc-ui');
    expect(realpath($hints['oidc-ui']))->toBe(realpath(__DIR__.'/../resources/lang'));
});
