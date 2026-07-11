<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Facades\Oidc;
use Bambamboole\LaravelOidc\Hooks\Context\UserinfoContext;
use Laravel\Passport\Passport;
use Workbench\App\Models\User;

it('adds custom claims to userinfo via the onUserinfo hook', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    Passport::actingAs($user, ['openid', 'profile']);
    Oidc::onUserinfo(fn (UserinfoContext $c) => $c->claims->set('teams', ['core']));

    $this->getJson('/oauth/userinfo')
        ->assertOk()
        ->assertJsonPath('teams', ['core'])
        ->assertJsonPath('sub', (string) $user->id);
});

it('cannot override sub from a userinfo hook', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    Passport::actingAs($user, ['openid']);
    Oidc::onUserinfo(fn (UserinfoContext $c) => $c->claims->set('sub', 'evil'));

    $this->getJson('/oauth/userinfo')->assertJsonPath('sub', (string) $user->id);
});
