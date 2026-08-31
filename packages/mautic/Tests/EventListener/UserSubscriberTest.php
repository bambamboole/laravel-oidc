<?php

declare(strict_types=1);

namespace MauticPlugin\LaravelOidcBundle\Tests\EventListener;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Event\AuthenticationEvent;
use Mautic\UserBundle\Security\Authentication\Token\PluginToken;
use Mautic\UserBundle\Security\Provider\UserProvider;
use Mautic\UserBundle\UserEvents;
use MauticPlugin\LaravelOidcBundle\EventListener\UserSubscriber;
use MauticPlugin\LaravelOidcBundle\Integration\LaravelOidcIntegration;
use MauticPlugin\LaravelOidcBundle\Tests\Support\TestIdp;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class UserSubscriberTest extends TestCase
{
    private UserSubscriber $subscriber;

    private LaravelOidcIntegration&MockObject $integration;

    private UserProvider&MockObject $userProvider;

    protected function setUp(): void
    {
        $this->subscriber = new UserSubscriber($this->createMock(CoreParametersHelper::class));
        $this->integration = $this->createMock(LaravelOidcIntegration::class);
        $this->userProvider = $this->createMock(UserProvider::class);
    }

    public function test_it_listens_to_pre_authentication(): void
    {
        self::assertSame(['onUserAuthentication', 0], UserSubscriber::getSubscribedEvents()[UserEvents::USER_PRE_AUTHENTICATION]);
    }

    public function test_it_redirects_to_the_provider_on_login(): void
    {
        $this->integration->method('getAuthLoginUrl')->willReturn('https://idp.test/oauth/authorize?state=x');

        $event = $this->event(isLoginCheck: false);

        $this->subscriber->onUserAuthentication($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://idp.test/oauth/authorize?state=x', $response->getTargetUrl());
        self::assertFalse($event->isAuthenticated());
    }

    public function test_it_authenticates_the_user_returned_by_the_callback(): void
    {
        $user = (new User)->setUsername('jane@example.com');
        $this->integration->method('ssoAuthCallback')->willReturn($user);
        $this->integration->method('shouldAutoCreateNewUser')->willReturn(true);
        $this->userProvider->expects(self::once())->method('saveUser')->with($user, true)->willReturn($user);

        $event = $this->event(isLoginCheck: true);

        $this->subscriber->onUserAuthentication($event);

        self::assertTrue($event->isAuthenticated());
        self::assertSame($user, $event->getUser());
    }

    public function test_it_ignores_other_authenticating_services(): void
    {
        $this->integration->expects(self::never())->method('getAuthLoginUrl');

        $event = $this->event(isLoginCheck: false, service: 'SomethingElse');

        $this->subscriber->onUserAuthentication($event);

        self::assertNull($event->getResponse());
    }

    public function test_it_authenticates_an_api_request_with_a_valid_provider_token(): void
    {
        $idp = TestIdp::make();
        $subscriber = $this->apiSubscriber($idp, [
            'oidc_api_user_email' => 'api@example.com',
            'oidc_api_allowed_client_ids' => ['artisan-os'],
        ]);

        $apiUser = (new User)->setUsername('api@example.com');
        $this->integration->method('getDecryptedApiKeys')->willReturn(['issuer' => $idp->issuer]);
        $this->userProvider->method('loadUserByIdentifier')->with('api@example.com')->willReturn($apiUser);
        $this->userProvider->expects(self::never())->method('saveUser');

        $event = $this->event(isLoginCheck: false, service: '', request: $this->apiRequest($idp->accessToken(['client_id' => 'artisan-os'])));

        $subscriber->onUserAuthentication($event);

        self::assertTrue($event->isAuthenticated());
        self::assertSame($apiUser, $event->getUser());
    }

    public function test_it_rejects_an_api_token_from_a_client_that_is_not_allowed(): void
    {
        $idp = TestIdp::make();
        $subscriber = $this->apiSubscriber($idp, [
            'oidc_api_user_email' => 'api@example.com',
            'oidc_api_allowed_client_ids' => ['artisan-os'],
        ]);

        $this->integration->method('getDecryptedApiKeys')->willReturn(['issuer' => $idp->issuer]);

        $event = $this->event(isLoginCheck: false, service: '', request: $this->apiRequest($idp->accessToken(['client_id' => 'stranger'])));

        $this->expectException(AuthenticationException::class);

        $subscriber->onUserAuthentication($event);
    }

    public function test_it_leaves_an_api_request_alone_while_api_auth_is_not_configured(): void
    {
        $idp = TestIdp::make();
        $subscriber = $this->apiSubscriber($idp, [
            'oidc_api_user_email' => null,
            'oidc_api_allowed_client_ids' => [],
        ]);

        $event = $this->event(isLoginCheck: false, service: '', request: $this->apiRequest($idp->accessToken()));

        $subscriber->onUserAuthentication($event);

        self::assertFalse($event->isAuthenticated());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function apiSubscriber(TestIdp $idp, array $parameters): UserSubscriber
    {
        $parametersHelper = $this->createMock(CoreParametersHelper::class);
        $parametersHelper->method('get')->willReturnCallback(
            static fn (string $name): mixed => $parameters[$name] ?? null,
        );

        $mock = new MockHandler([
            new Response(200, [], json_encode($idp->discoveryDocument(), JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($idp->jwksDocument(), JSON_THROW_ON_ERROR)),
        ]);

        return new UserSubscriber($parametersHelper, null, new Client(['handler' => HandlerStack::create($mock)]));
    }

    private function apiRequest(string $token): Request
    {
        $request = new Request;
        $request->headers->set(UserSubscriber::API_TOKEN_HEADER, $token);

        return $request;
    }

    private function event(bool $isLoginCheck, string $service = LaravelOidcIntegration::NAME, ?Request $request = null): AuthenticationEvent
    {
        return new AuthenticationEvent(
            null,
            new PluginToken('main', $service),
            $this->userProvider,
            $request ?? new Request,
            $isLoginCheck,
            $service,
            [LaravelOidcIntegration::NAME => $this->integration],
        );
    }
}
