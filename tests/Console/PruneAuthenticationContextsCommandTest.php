<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Auth\Models\AccessTokenContext;
use Bambamboole\LaravelOidc\Auth\Models\AuthenticationContext;
use Bambamboole\LaravelOidc\Auth\Models\OidcSession;
use Bambamboole\LaravelOidc\Auth\Models\SessionParticipant;
use Bambamboole\LaravelOidc\Auth\SessionRegistry;

it('prunes expired contexts and keeps live ones', function () {
    $live = new AuthenticationContext;
    $live->user_id = '1';
    $live->amr = ['pwd'];
    $live->acr = '1';
    $live->auth_time = time();
    $live->id_token_claims = [];
    $live->access_token_claims = [];
    $live->expires_at = now()->addDay();
    $live->created_at = now();
    $live->save();

    $expired = new AuthenticationContext;
    $expired->user_id = '2';
    $expired->amr = ['pwd'];
    $expired->acr = '1';
    $expired->auth_time = time();
    $expired->id_token_claims = [];
    $expired->access_token_claims = [];
    $expired->expires_at = now()->subDay();
    $expired->created_at = now()->subDays(40);
    $expired->save();

    // a stale link row beyond the retention horizon
    $staleLink = new AccessTokenContext;
    $staleLink->access_token_id = 'stale';
    $staleLink->context_id = $expired->id;
    $staleLink->created_at = now()->subDays(400);
    $staleLink->save();

    // an in-horizon link row that should be kept
    $freshLink = new AccessTokenContext;
    $freshLink->access_token_id = 'fresh';
    $freshLink->context_id = $expired->id;
    $freshLink->created_at = now();
    $freshLink->save();

    $registry = app(SessionRegistry::class);

    $oldSid = $registry->start('3');
    OidcSession::query()->whereKey($oldSid)->update(['expires_at' => now()->subDays(2)]);
    $registry->recordParticipant($oldSid, 'some-client');

    $recentSid = $registry->start('4');
    OidcSession::query()->whereKey($recentSid)->update(['expires_at' => now()->subMinute()]);
    $registry->recordParticipant($recentSid, 'some-client');

    $this->artisan('oidc:prune-authentication-contexts')->assertExitCode(0);

    expect(AuthenticationContext::query()->pluck('id')->all())->toBe([$live->id])
        ->and(AccessTokenContext::query()->where('access_token_id', 'stale')->exists())->toBeFalse()
        ->and(AccessTokenContext::query()->where('access_token_id', 'fresh')->exists())->toBeTrue()
        ->and(OidcSession::query()->whereKey($oldSid)->exists())->toBeFalse()
        ->and(SessionParticipant::query()->where('sid', $oldSid)->exists())->toBeFalse()
        ->and(OidcSession::query()->whereKey($recentSid)->exists())->toBeTrue()
        ->and(SessionParticipant::query()->where('sid', $recentSid)->exists())->toBeTrue();
});
