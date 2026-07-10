<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Artifact;
use Bambamboole\LaravelOidc\Hooks\ClaimHooks;
use Bambamboole\LaravelOidc\Hooks\ClaimsBag;
use Bambamboole\LaravelOidc\Hooks\Context\ClientCredentialsContext;
use Bambamboole\LaravelOidc\Hooks\Trigger;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Bridge\Client;

it('runs registered hooks in order and lets later hooks override earlier ones', function () {
    Oidc::onClientCredentials(fn (ClientCredentialsContext $c) => $c->accessToken->set('a', 1)->set('b', 1));
    Oidc::onClientCredentials(fn (ClientCredentialsContext $c) => $c->accessToken->set('b', 2));

    $bag = new ClaimsBag(Artifact::AccessToken);
    $context = new ClientCredentialsContext(new Client('cid', 'n', ['https://x/cb']), ['openid'], $bag);

    app(ClaimHooks::class)->run(Trigger::ClientCredentials, $context);

    expect($bag->all())->toBe(['a' => 1, 'b' => 2]);
});

it('isolates triggers from each other', function () {
    Oidc::onRefresh(fn ($c) => $c->accessToken->set('should_not_run', true));

    $bag = new ClaimsBag(Artifact::AccessToken);
    $context = new ClientCredentialsContext(new Client('cid', 'n', ['https://x/cb']), [], $bag);
    app(ClaimHooks::class)->run(Trigger::ClientCredentials, $context);

    expect($bag->all())->toBe([]);
});

it('catches a throwing hook, logs it, and continues', function () {
    Log::spy();
    Oidc::onClientCredentials(function () {
        throw new RuntimeException('boom');
    });
    Oidc::onClientCredentials(fn (ClientCredentialsContext $c) => $c->accessToken->set('after', true));

    $bag = new ClaimsBag(Artifact::AccessToken);
    $context = new ClientCredentialsContext(new Client('cid', 'n', ['https://x/cb']), [], $bag);
    app(ClaimHooks::class)->run(Trigger::ClientCredentials, $context);

    expect($bag->all())->toBe(['after' => true]);
    Log::shouldHaveReceived('error')->once();
});
