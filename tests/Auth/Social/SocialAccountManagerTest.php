<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Social\Models\SocialAccount;
use Bambamboole\LaravelOidc\Auth\Social\SocialAccountManager;
use Bambamboole\LaravelOidc\Auth\Social\SocialUser;
use Bambamboole\LaravelOidc\Facades\Oidc;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Workbench\App\Models\User;

/**
 * @param  array<string, mixed>  $overrides
 */
function socialUser(array $overrides = []): SocialUser
{
    return new SocialUser(...array_merge([
        'id' => 'g-123',
        'email' => 'm@example.com',
        'emailVerified' => true,
        'name' => 'M',
        'nickname' => null,
        'avatar' => null,
        'raw' => ['sub' => 'g-123'],
        'accessToken' => 'at-1',
        'refreshToken' => 'rt-1',
        'expiresIn' => 3600,
    ], $overrides));
}

function userProvider(): UserProvider
{
    $guard = Auth::guard('identity');

    if (! $guard instanceof SessionGuard) {
        throw new RuntimeException('Expected the identity guard to be a session guard.');
    }

    return $guard->getProvider();
}

it('resolves an already linked account and refreshes its tokens', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);
    $manager = app(SocialAccountManager::class);
    $manager->link($user, 'google', socialUser(['accessToken' => 'old-at']));

    $resolved = $manager->resolveUser('google', socialUser(['accessToken' => 'new-at']), userProvider());

    expect($resolved->is($user))->toBeTrue()
        ->and(SocialAccount::query()->count())->toBe(1)
        ->and(SocialAccount::query()->first()->access_token)->toBe('new-at');
});

it('links by verified email when enabled', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $resolved = app(SocialAccountManager::class)->resolveUser('google', socialUser(), userProvider());

    expect($resolved->is($user))->toBeTrue()
        ->and(SocialAccount::query()->where('provider', 'google')->where('provider_user_id', 'g-123')->exists())->toBeTrue();
});

it('does not link by unverified email', function () {
    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    $resolved = app(SocialAccountManager::class)->resolveUser('google', socialUser(['emailVerified' => false]), userProvider());

    expect($resolved)->toBeNull();
});

it('does not link by email when disabled', function () {
    config()->set('oidc.social.link_by_verified_email', false);
    User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

    expect(app(SocialAccountManager::class)->resolveUser('google', socialUser(), userProvider()))->toBeNull();
});

it('provisions a new user via the registered action', function () {
    Oidc::createUsersFromSocialUsing(fn (SocialUser $socialUser, string $provider): User => User::create([
        'name' => $socialUser->name ?? 'Unknown',
        'email' => $socialUser->email,
        'password' => Str::random(40),
    ]));

    $resolved = app(SocialAccountManager::class)->resolveUser('google', socialUser(), userProvider());

    expect($resolved)->toBeInstanceOf(User::class)
        ->and(SocialAccount::query()->count())->toBe(1);

    $this->assertDatabaseHas('users', ['email' => 'm@example.com']);
});

it('returns null when provisioning is disabled', function () {
    config()->set('oidc.social.auto_provision', false);
    Oidc::createUsersFromSocialUsing(fn (): User => throw new LogicException('must not be called'));

    expect(app(SocialAccountManager::class)->resolveUser('google', socialUser(), userProvider()))->toBeNull();
});

it('returns null when no provisioning action is registered', function () {
    expect(app(SocialAccountManager::class)->resolveUser('google', socialUser(), userProvider()))->toBeNull();
});
