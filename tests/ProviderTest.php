<?php
declare(strict_types=1);

it('registers the oidc config', function () {
    expect(config('oidc.id_token_ttl'))->toBe(3600)
        ->and(config('oidc.endpoints.userinfo'))->toBeTrue();
});
