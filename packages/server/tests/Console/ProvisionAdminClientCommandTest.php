<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Server\Audit\AuditEvent;
use Bambamboole\LaravelOidc\Server\Audit\AuditEventType;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

/**
 * @param  array<string, mixed>  $options
 * @return array{id: string, secret: ?string}
 */
function provisionAdminClient(array $options = []): array
{
    expect(Artisan::call('oidc:admin-client', $options))->toBe(0);
    $output = Artisan::output();

    preg_match('/OIDC_ADMIN_CLIENT_ID=(\S+)/', $output, $id);
    preg_match('/OIDC_ADMIN_CLIENT_SECRET=(\S+)/', $output, $secret);

    return ['id' => $id[1] ?? '', 'secret' => $secret[1] ?? null];
}

it('refuses to run while administration is disabled', function () {
    $this->artisan('oidc:admin-client')
        ->expectsOutputToContain('Client administration is disabled')
        ->assertExitCode(2);

    expect(Passport::client()->newQuery()->count())->toBe(0);
});

it('creates the admin client once and prints its credentials', function () {
    config(['oidc.admin.enabled' => true]);

    $first = provisionAdminClient(['--name' => 'Pulumi']);
    $client = Passport::client()->newQuery()->findOrFail($first['id']);

    expect($first['secret'])->toBeString()
        ->and($client->getAttribute('name'))->toBe('Pulumi')
        ->and($client->getRawOriginal('oidc_provisioning_key'))->toBe('admin')
        ->and($client->getAttribute('grant_types'))->toBe(['client_credentials'])
        ->and($client->getAttribute('scopes'))->toBe(['oidc:admin'])
        ->and($client->confidential())->toBeTrue()
        ->and($client->firstParty())->toBeTrue();

    $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $first['id'],
        'client_secret' => $first['secret'],
        'scope' => 'oidc:admin',
    ])->assertOk();

    $second = provisionAdminClient(['--name' => 'Pulumi renamed']);

    expect($second['id'])->toBe($first['id'])
        ->and($second['secret'])->toBeNull()
        ->and($client->refresh()->getAttribute('name'))->toBe('Pulumi renamed')
        ->and(Passport::client()->newQuery()->count())->toBe(1);
});

it('rotates the secret on request', function () {
    config(['oidc.admin.enabled' => true]);
    $sink = fakeAudit();

    $created = provisionAdminClient();
    $rotated = provisionAdminClient(['--rotate' => true]);

    $token = fn (?string $secret) => $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $created['id'],
        'client_secret' => $secret,
        'scope' => 'oidc:admin',
    ]);

    expect($rotated['id'])->toBe($created['id'])
        ->and($rotated['secret'])->toBeString()->not->toBe($created['secret']);

    $token($rotated['secret'])->assertOk();
    $token($created['secret'])->assertStatus(401);

    $sink->assertRecorded(AuditEventType::ClientProvisioned, fn (AuditEvent $event): bool => $event->clientId === $created['id']
        && $event->context['provisioning_key'] === 'admin' && $event->context['secret_rotated'] === true);
});

it('refuses a revoked admin client', function () {
    config(['oidc.admin.enabled' => true]);

    $created = provisionAdminClient();
    Passport::client()->newQuery()->whereKey($created['id'])->update(['revoked' => true]);

    $this->artisan('oidc:admin-client')->expectsOutputToContain('revoked')->assertExitCode(1);
});
