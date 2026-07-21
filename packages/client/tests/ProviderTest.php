<?php

declare(strict_types=1);

it('boots the service provider and loads the relying-party config', function () {
    expect(config('oidc-client.scopes'))->toBe(['openid', 'profile', 'email'])
        ->and(config('oidc-client.login_guard'))->toBe('web');
});
