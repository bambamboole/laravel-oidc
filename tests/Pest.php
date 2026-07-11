<?php
declare(strict_types=1);

use Bambamboole\LaravelOidc\Tests\TestCase;
use Bambamboole\LaravelOidc\Token\OidcAccessToken;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\Scope as BridgeScope;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\CryptTrait;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in(__DIR__);

/**
 * Mirrors League\OAuth2\Server\ResponseTypes\BearerTokenResponse::generateHttpResponse(),
 * which is how Passport actually produces refresh_token values on the wire.
 *
 * @param  array<string, mixed>  $payload
 */
function encryptRefreshTokenPayload(array $payload): string
{
    $encrypter = new class
    {
        use CryptTrait;

        public function encryptPayload(string $data): string
        {
            return $this->encrypt($data);
        }
    };

    $encrypter->setEncryptionKey(Passport::tokenEncryptionKey(app(EncrypterContract::class)));

    return $encrypter->encryptPayload((string) json_encode($payload));
}

/**
 * Creates a persisted refresh-token + linked access-token pair and returns the
 * encrypted refresh token value League/Passport would hand back to a client.
 *
 * @return array{0: string, 1: RefreshToken, 2: Token}
 */
function issueRefreshToken(mixed $test, ?string $clientId = null, bool $expired = false): array
{
    $accessTokenId = Str::random(80);
    $refreshTokenId = Str::random(80);

    $accessToken = Passport::token();
    $accessToken->forceFill([
        'id' => $accessTokenId,
        'user_id' => $test->user->id,
        'client_id' => $test->client->id,
        'scopes' => ['openid'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ])->save();

    $refreshToken = Passport::refreshToken();
    $refreshToken->forceFill([
        'id' => $refreshTokenId,
        'access_token_id' => $accessTokenId,
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ])->save();

    $encrypted = encryptRefreshTokenPayload([
        'client_id' => $clientId ?? $test->client->id,
        'refresh_token_id' => $refreshTokenId,
        'access_token_id' => $accessTokenId,
        'scopes' => ['openid'],
        'user_id' => $test->user->id,
        'expire_time' => $expired ? now()->subDay()->timestamp : now()->addDay()->timestamp,
    ]);

    return [$encrypted, $refreshToken, $accessToken];
}

/**
 * Mints an RFC 9068 access token addressed to $clientId and persists a matching
 * Passport token row so TokenInspector::accessToken() resolves it.
 *
 * @param  string[]  $scopeIds
 */
function mintExchangeSubjectToken(
    string $clientId,
    string $userId,
    array $scopeIds,
    ?DateTimeImmutable $expiresAt = null,
    bool $revoked = false,
): string {
    $tokenId = Str::random(80);
    $expiresAt ??= new DateTimeImmutable('+1 hour');

    $subject = new OidcAccessToken(
        $userId,
        array_map(fn (string $scope) => new BridgeScope($scope), $scopeIds),
        new BridgeClient($clientId, 'RP', ['https://rp.test/cb']),
    );
    $subject->setIdentifier($tokenId);
    $subject->setAudience($clientId);
    $subject->setExpiryDateTime($expiresAt);
    $subject->setPrivateKey(new CryptKey(__DIR__.'/fixtures/oauth-private.key', null, false));

    Passport::token()->forceFill([
        'id' => $tokenId,
        'user_id' => $userId,
        'client_id' => $clientId,
        'scopes' => $scopeIds,
        'revoked' => $revoked,
        'expires_at' => $expiresAt,
    ])->save();

    return $subject->toString();
}
