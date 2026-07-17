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
        // The token leg is intentionally unasserted (see the trait docblock),
        // so a broken consumer app can return a non-JSON body (e.g. an HTML
        // 500 page). Guard the decode so constructing the DTO never throws —
        // callers assert on `$response` / `json()` for the error case, and
        // that contract must hold even when the body isn't decodable JSON.
        $decoded = json_decode($response->getContent(), true);
        $isJsonObject = is_array($decoded);

        return new self(
            $response,
            $isJsonObject ? $response->json('access_token') : null,
            $isJsonObject ? $response->json('id_token') : null,
            $isJsonObject ? $response->json('refresh_token') : null,
        );
    }

    public function json(?string $key = null): mixed
    {
        return $this->response->json($key);
    }
}
