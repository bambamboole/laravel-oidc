<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Testing;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The outcome of {@see InteractsWithOidc::authorizeAndApprove()}. The token
 * fields are null when the token endpoint returned an error — assert on
 * `$response` / `json()` in that case.
 */
final readonly class AuthorizationCodeResult
{
    /**
     * @param  TestResponse<Response>  $response
     */
    public function __construct(
        public TestResponse $response,
        public ?string $accessToken,
        public ?string $idToken,
        public ?string $refreshToken,
    ) {}

    /**
     * @param  TestResponse<Response>  $response
     */
    public static function fromResponse(TestResponse $response): self
    {
        return new self(
            $response,
            $response->json('access_token'),
            $response->json('id_token'),
            $response->json('refresh_token'),
        );
    }

    public function json(?string $key = null): mixed
    {
        return $this->response->json($key);
    }
}
