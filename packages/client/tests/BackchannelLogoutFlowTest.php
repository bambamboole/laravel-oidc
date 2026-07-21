<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Tests;

use Bambamboole\LaravelOidcClient\Facades\OidcClient;
use Bambamboole\LaravelOidcClient\Tests\Support\BackchannelLogoutEnabledTestCase;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

class BackchannelLogoutFlowTest extends BackchannelLogoutEnabledTestCase
{
    public function test_a_provider_logout_token_tears_down_the_session_end_to_end(): void
    {
        $fake = OidcClient::fake()->clientId('client-123');

        // A route through the `web` group, exactly like an app-defined page, so we
        // exercise the auto-appended EnforceBackchannelLogout middleware for real.
        Route::get('/session-status', fn () => auth()->check() ? 'authenticated' : 'guest')
            ->middleware('web');

        $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => 'secret']);

        $logoutToken = $fake->forUser($user)->logoutToken(['sid' => 'sess-e2e']);

        // The user is authenticated with a session carrying the provider's sid.
        $this->actingAs($user)->withSession(['oidc-client.sid' => 'sess-e2e']);
        $this->get('/session-status')->assertSeeText('authenticated');

        // The provider POSTs a back-channel logout token for that sid.
        $this->post('/oidc/backchannel-logout', ['logout_token' => $logoutToken])->assertOk();

        // The next request through the web group carries the same sid and is denylisted:
        // the auto-appended middleware logs it out before the route handler runs.
        $this->actingAs($user)->withSession(['oidc-client.sid' => 'sess-e2e']);
        $this->get('/session-status')->assertSeeText('guest');

        $fake->assertBackchannelLogoutProcessed('sess-e2e');
        $this->assertGuest();
    }
}
