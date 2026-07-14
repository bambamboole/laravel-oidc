<?php

declare(strict_types=1);

use Bambamboole\LaravelOidcClient\Http\Middleware\EnforceBackchannelLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Workbench\App\Models\User;

beforeEach(fn () => config()->set('oidc-client.backchannel_logout.enabled', true));

it('logs out a request whose session sid is revoked', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'x']);
    $this->actingAs($user);
    session()->put('oidc-client.sid', 'sess-x');
    Cache::put('oidc-client:bclo:revoked:sess-x', true, now()->addMinutes(120));

    $request = Request::create('/dashboard');
    $request->setLaravelSession(session()->driver());

    app(EnforceBackchannelLogout::class)->handle($request, fn ($r) => response('ok'));

    expect(Auth::guard(config('oidc-client.login_guard', 'web'))->check())->toBeFalse();
});

it('passes through an unmarked session', function () {
    $user = User::create(['name' => 'M', 'email' => 'm2@example.com', 'password' => 'x']);
    $this->actingAs($user);
    session()->put('oidc-client.sid', 'sess-y');

    $request = Request::create('/dashboard');
    $request->setLaravelSession(session()->driver());

    app(EnforceBackchannelLogout::class)->handle($request, fn ($r) => response('ok'));

    expect(Auth::guard(config('oidc-client.login_guard', 'web'))->check())->toBeTrue();
});
