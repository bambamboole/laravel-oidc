<?php
declare(strict_types=1);

/**
 * OpenID Connect Core 1.0 §5.3 (UserInfo endpoint)
 */

use Laravel\Passport\Passport;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'M', 'email' => 'm@example.com', 'email_verified_at' => now(), 'password' => 'x']);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/oauth/userinfo')->assertUnauthorized();
});

it('rejects tokens without the openid scope', function () {
    Passport::actingAs($this->user, ['email']);

    $this->getJson('/oauth/userinfo')->assertForbidden();
});

it('returns sub plus scope-filtered claims', function () {
    Passport::actingAs($this->user, ['openid', 'email']);

    $this->getJson('/oauth/userinfo')
        ->assertOk()
        ->assertExactJson([
            'sub' => (string) $this->user->id,
            'email' => 'm@example.com',
            'email_verified' => true,
        ]);
});

it('includes profile claims when granted', function () {
    Passport::actingAs($this->user, ['openid', 'profile', 'email']);

    $response = $this->getJson('/oauth/userinfo')->assertOk();

    expect($response->json('name'))->toBe('M')
        ->and($response->json('sub'))->toBe((string) $this->user->id);
});

it('accepts POST as required by the spec', function () {
    Passport::actingAs($this->user, ['openid']);

    $this->postJson('/oauth/userinfo')->assertOk()->assertJson(['sub' => (string) $this->user->id]);
});
