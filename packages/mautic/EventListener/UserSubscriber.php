<?php

declare(strict_types=1);

namespace MauticPlugin\LaravelOidcBundle\EventListener;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Mautic\CoreBundle\Helper\CacheStorageHelper;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Event\AuthenticationEvent;
use Mautic\UserBundle\UserEvents;
use MauticPlugin\LaravelOidcBundle\Discovery\MetadataResolver;
use MauticPlugin\LaravelOidcBundle\Integration\LaravelOidcIntegration;
use MauticPlugin\LaravelOidcBundle\Security\ApiTokenValidator;
use MauticPlugin\LaravelOidcBundle\Security\JwksKeySet;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UserSubscriber implements EventSubscriberInterface
{
    public const API_TOKEN_HEADER = 'X-Api-Token';

    public function __construct(
        private readonly CoreParametersHelper $coreParametersHelper,
        private readonly ?CacheStorageHelper $cacheStorageHelper = null,
        private readonly ?ClientInterface $httpClient = null,
    ) {}

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserEvents::USER_PRE_AUTHENTICATION => ['onUserAuthentication', 0],
        ];
    }

    public function onUserAuthentication(AuthenticationEvent $event): void
    {
        $apiToken = $event->getRequest()->headers->get(self::API_TOKEN_HEADER);

        if (is_string($apiToken) && $apiToken !== '') {
            $this->authenticateApiRequest($event, $apiToken);

            return;
        }

        if ($event->getAuthenticatingService() !== LaravelOidcIntegration::NAME) {
            return;
        }

        $integration = $event->getIntegration(LaravelOidcIntegration::NAME);

        if (! $integration instanceof LaravelOidcIntegration) {
            throw new \RuntimeException('The OpenID Connect integration is not available.');
        }

        $integration->setCoreParametersHelper($this->coreParametersHelper);
        $integration->setUserProvider($event->getUserProvider());

        if (! $event->isLoginCheck()) {
            $event->setResponse(new RedirectResponse($integration->getAuthLoginUrl()));

            return;
        }

        $user = $integration->ssoAuthCallback();

        if (! $user instanceof User) {
            throw new AuthenticationException('mautic.user.auth.error.invalidlogin');
        }

        $event->setIsAuthenticated(LaravelOidcIntegration::NAME, $user, $integration->shouldAutoCreateNewUser());
    }

    /**
     * Authenticates a machine-to-machine API request carrying an issuer-signed
     * access token in the X-Api-Token header. Bearer stays with Mautic's own
     * OAuth stack — its authenticator claims every request that carries one.
     */
    private function authenticateApiRequest(AuthenticationEvent $event, string $apiToken): void
    {
        $apiUserEmail = $this->coreParametersHelper->get('oidc_api_user_email');
        $allowedClientIds = $this->coreParametersHelper->get('oidc_api_allowed_client_ids');
        $allowedClientIds = array_values(array_filter(is_array($allowedClientIds) ? $allowedClientIds : [], is_string(...)));

        if (! is_string($apiUserEmail) || trim($apiUserEmail) === '' || $allowedClientIds === []) {
            return;
        }

        $integration = $event->getIntegration(LaravelOidcIntegration::NAME);

        if (! $integration instanceof LaravelOidcIntegration) {
            return;
        }

        $issuer = $integration->getDecryptedApiKeys()['issuer'] ?? null;

        if (! is_string($issuer) || trim($issuer) === '') {
            throw new AuthenticationException('The OpenID Connect issuer is not configured.');
        }

        $httpClient = $this->httpClient ?? new Client;
        $metadata = (new MetadataResolver($httpClient, $this->cacheStorageHelper))->resolve($issuer);

        (new ApiTokenValidator(new JwksKeySet($httpClient, $this->cacheStorageHelper)))
            ->validate($apiToken, $metadata, $allowedClientIds);

        $user = $event->getUserProvider()->loadUserByIdentifier(trim($apiUserEmail));

        $event->setIsAuthenticated(LaravelOidcIntegration::NAME, $user, false);
    }
}
