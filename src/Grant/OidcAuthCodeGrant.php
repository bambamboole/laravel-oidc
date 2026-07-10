<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Grant;

use Bambamboole\LaravelOidc\Responses\IdTokenResponse;
use DateInterval;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

class OidcAuthCodeGrant extends AuthCodeGrant
{
    /**
     * league v9 declares AuthCodeGrant::$authCodeTTL private, so the forked
     * completeAuthorizationRequest() below cannot read the parent's copy; mirror it here.
     */
    protected DateInterval $authCodeTTL;

    public function __construct(
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        DateInterval $authCodeTTL,
    ) {
        parent::__construct($authCodeRepository, $refreshTokenRepository, $authCodeTTL);
        $this->authCodeTTL = $authCodeTTL;
    }

    protected function createAuthorizationRequest(): AuthorizationRequestInterface
    {
        return new OidcAuthorizationRequest;
    }

    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $authorizationRequest = parent::validateAuthorizationRequest($request);

        if ($authorizationRequest instanceof OidcAuthorizationRequest) {
            $authorizationRequest->setNonce($this->getQueryStringParameter('nonce', $request));
        }

        return $authorizationRequest;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        if ($responseType instanceof IdTokenResponse) {
            $encryptedAuthCode = $this->getRequestParameter('code', $request);

            if ($encryptedAuthCode !== null) {
                try {
                    $payload = json_decode($this->decrypt($encryptedAuthCode));
                    $responseType->setNonce($payload->nonce ?? null);
                    $responseType->setAuthTime(isset($payload->auth_time) ? (int) $payload->auth_time : null);
                } catch (\Throwable) {
                    // Parent will reject the malformed code with a proper OAuth error.
                }
            }
        }

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }

    /**
     * Fork of League\OAuth2\Server\Grant\AuthCodeGrant::completeAuthorizationRequest() (league v9),
     * kept byte-identical except for the added `nonce` and `auth_time` payload keys so future
     * league diffs remain easy to port.
     */
    public function completeAuthorizationRequest(AuthorizationRequestInterface $authorizationRequest): ResponseTypeInterface
    {
        if ($authorizationRequest->getUser() instanceof UserEntityInterface === false) {
            throw new LogicException('An instance of UserEntityInterface should be set on the AuthorizationRequest');
        }

        $finalRedirectUri = $authorizationRequest->getRedirectUri()
                          ?? $this->getClientRedirectUri($authorizationRequest->getClient());

        if ($authorizationRequest->isAuthorizationApproved() === true) {
            $authCode = $this->issueAuthCode(
                $this->authCodeTTL,
                $authorizationRequest->getClient(),
                $authorizationRequest->getUser()->getIdentifier(),
                $authorizationRequest->getRedirectUri(),
                $authorizationRequest->getScopes()
            );

            $payload = [
                'client_id' => $authCode->getClient()->getIdentifier(),
                'redirect_uri' => $authCode->getRedirectUri(),
                'auth_code_id' => $authCode->getIdentifier(),
                'scopes' => $authCode->getScopes(),
                'user_id' => $authCode->getUserIdentifier(),
                'expire_time' => (new DateTimeImmutable)->add($this->authCodeTTL)->getTimestamp(),
                'code_challenge' => $authorizationRequest->getCodeChallenge(),
                'code_challenge_method' => $authorizationRequest->getCodeChallengeMethod(),
                'nonce' => $authorizationRequest instanceof OidcAuthorizationRequest
                    ? $authorizationRequest->getNonce()
                    : null,
                'auth_time' => $this->currentAuthTime(),
            ];

            $jsonPayload = json_encode($payload);

            if ($jsonPayload === false) {
                throw new LogicException('An error was encountered when JSON encoding the authorization request response');
            }

            $response = new RedirectResponse;
            $response->setRedirectUri(
                $this->makeRedirectUri(
                    $finalRedirectUri,
                    [
                        'code' => $this->encrypt($jsonPayload),
                        'state' => $authorizationRequest->getState(),
                    ]
                )
            );

            return $response;
        }

        throw OAuthServerException::accessDenied(
            'The user denied the request',
            $this->makeRedirectUri(
                $finalRedirectUri,
                ['state' => $authorizationRequest->getState()]
            )
        );
    }

    private function currentAuthTime(): int
    {
        if (app()->bound('session.store') && app('session.store')->isStarted()) {
            return (int) app('session.store')->get('oidc.auth_time', time());
        }

        return time();
    }
}
