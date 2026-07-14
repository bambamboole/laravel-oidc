<?php
// tests/Session/StartOidcSessionTest.php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Models\OidcSession;
use Bambamboole\LaravelOidc\Session\StartOidcSession;
use Illuminate\Auth\Events\Login;
use Workbench\App\Models\User;

it('creates a session and stores the sid on an oidc-guard login', function () {
    $this->startSession();
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'email_verified_at' => now(), 'password' => 'x']);

    app(StartOidcSession::class)->handle(new Login(config('passport.guard'), $user, false));

    expect(OidcSession::query()->where('user_id', (string) $user->id)->count())->toBe(1)
        ->and(session()->get('oidc.sid'))->toBeString();
});

it('ignores logins on other guards', function () {
    $user = User::create(['name' => 'M', 'email' => 'm2@example.com', 'email_verified_at' => now(), 'password' => 'x']);

    app(StartOidcSession::class)->handle(new Login('web', $user, false));

    expect(OidcSession::query()->count())->toBe(0)
        ->and(session()->has('oidc.sid'))->toBeFalse();
});
