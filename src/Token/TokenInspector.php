<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Token;

use Illuminate\Contracts\Encryption\Encrypter;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use League\OAuth2\Server\CryptTrait;
use Throwable;

class TokenInspector
{
    use CryptTrait;

    public function __construct(Encrypter $encrypter)
    {
        $this->setEncryptionKey(Passport::tokenEncryptionKey($encrypter));
    }

    public function accessToken(string $jwt): ?Token
    {
        try {
            $parsed = (new Parser(new JoseEncoder))->parse($jwt);
        } catch (Throwable) {
            return null;
        }

        if (! $parsed instanceof Plain || ! (new Validator)->validate(
            $parsed,
            new SignedWith(new Sha256, InMemory::plainText(PassportKeys::publicKey())),
        )) {
            return null;
        }

        $jti = $parsed->claims()->get('jti');

        return is_string($jti) ? Passport::token()->newQuery()->find($jti) : null;
    }

    public function parse(string $jwt): ?Plain
    {
        try {
            $parsed = (new Parser(new JoseEncoder))->parse($jwt);
        } catch (Throwable) {
            return null;
        }

        if (! $parsed instanceof Plain || ! (new Validator)->validate(
            $parsed,
            new SignedWith(new Sha256, InMemory::plainText(PassportKeys::publicKey())),
        )) {
            return null;
        }

        return $parsed;
    }

    public function refreshTokenPayload(string $encrypted): ?object
    {
        try {
            $payload = json_decode($this->decrypt($encrypted));
        } catch (Throwable) {
            return null;
        }

        return is_object($payload) ? $payload : null;
    }
}
