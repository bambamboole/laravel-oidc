<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Hooks\Artifact;
use Bambamboole\LaravelOidc\Hooks\ClaimsBag;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;

it('stores and returns non-protected claims', function () {
    $bag = new ClaimsBag(Artifact::AccessToken);
    $bag->set('roles', ['admin'])->set('tenant', 'acme');

    expect($bag->all())->toBe(['roles' => ['admin'], 'tenant' => 'acme'])
        ->and($bag->has('roles'))->toBeTrue();
});

it('forgets a claim', function () {
    $bag = (new ClaimsBag(Artifact::IdToken))->set('a', 1)->forget('a');

    expect($bag->has('a'))->toBeFalse();
});

it('silently drops protected claims common to all artifacts and logs', function () {
    $logger = new class extends AbstractLogger
    {
        public int $warnings = 0;

        public function log($level, string|Stringable $message, array $context = []): void
        {
            if ($level === 'warning') {
                $this->warnings++;
            }
        }
    };
    Log::swap($logger);
    $bag = new ClaimsBag(Artifact::Userinfo);

    foreach (['iss', 'sub', 'aud', 'exp', 'iat', 'nbf', 'jti'] as $claim) {
        $bag->set($claim, 'x');
    }

    expect($bag->all())->toBe([])
        ->and($logger->warnings)->toBe(7);
});

it('drops id_token-specific protected claims but allows them on access tokens', function () {
    $idToken = new ClaimsBag(Artifact::IdToken);
    $idToken->set('nonce', 'n')->set('at_hash', 'h')->set('azp', 'c')->set('acr', '1');
    expect($idToken->all())->toBe([]);

    $accessToken = new ClaimsBag(Artifact::AccessToken);
    $accessToken->set('nonce', 'n');
    expect($accessToken->all())->toBe(['nonce' => 'n']);
});

it('drops access-token-specific protected claims but allows them on id_tokens', function () {
    $accessToken = new ClaimsBag(Artifact::AccessToken);
    $accessToken->set('scope', 'a b')->set('scopes', ['a'])->set('client_id', 'x')->set('cnf', []);
    expect($accessToken->all())->toBe([]);

    $idToken = new ClaimsBag(Artifact::IdToken);
    $idToken->set('scope', 'a b');
    expect($idToken->all())->toBe(['scope' => 'a b']);
});
