<?php

declare(strict_types=1);

it('boots and registers the oidc-ui translation namespace', function () {
    expect(app('translator')->hasForLocale('oidc-ui::auth.login.title', 'en'))->toBeTrue();
    expect(config('oidc-ui.brand_icon'))->toBe('logo');
});
