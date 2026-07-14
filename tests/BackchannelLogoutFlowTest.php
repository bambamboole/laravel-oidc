<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests;

use Bambamboole\LaravelOidcClient\Tests\Support\BackchannelLogoutEnabledTestCase;
use Bambamboole\LaravelOidcClient\Tests\Support\FakeOidcProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

class BackchannelLogoutFlowTest extends BackchannelLogoutEnabledTestCase
{
    public function test_a_provider_logout_token_tears_down_the_session_end_to_end(): void
    {
        config()->set('oidc-client.issuer', 'https://id.example.com');
        config()->set('oidc-client.client_id', 'client-123');

        $provider = new FakeOidcProvider;
        Http::fake([
            'https://id.example.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://id.example.com',
                'authorization_endpoint' => 'https://id.example.com/oauth/authorize',
                'token_endpoint' => 'https://id.example.com/oauth/token',
                'jwks_uri' => 'https://id.example.com/.well-known/jwks.json',
            ]),
            'https://id.example.com/.well-known/jwks.json' => Http::response([
                'keys' => $provider->rsaJwks('key-1'),
            ]),
        ]);

        // A route through the `web` group, exactly like an app-defined page, so we
        // exercise the auto-appended EnforceBackchannelLogout middleware for real.
        Route::get('/session-status', fn () => auth()->check() ? 'authenticated' : 'guest')
            ->middleware('web');

        $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

        $logoutToken = $provider->logoutToken([
            'iss' => 'https://id.example.com', 'aud' => 'client-123', 'sub' => (string) $user->getKey(),
            'sid' => 'sess-e2e', 'iat' => time(), 'exp' => time() + 120, 'jti' => 'jti-e2e',
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => (object) []],
        ], 'key-1');

        // The user is authenticated with a session carrying the provider's sid.
        $this->actingAs($user)->withSession(['oidc-client.sid' => 'sess-e2e']);
        $this->get('/session-status')->assertSeeText('authenticated');

        // The provider POSTs a back-channel logout token for that sid.
        $this->post('/oidc/backchannel-logout', ['logout_token' => $logoutToken])->assertOk();
        $this->assertTrue(Cache::has('oidc-client:bclo:revoked:sess-e2e'));

        // The next request through the web group carries the same sid and is denylisted:
        // the auto-appended middleware logs it out before the route handler runs.
        $this->actingAs($user)->withSession(['oidc-client.sid' => 'sess-e2e']);
        $this->get('/session-status')->assertSeeText('guest');

        $this->assertGuest();
    }
}
