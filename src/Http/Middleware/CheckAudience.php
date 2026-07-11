<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Enforces that the bearer token presented to a resource-server route is an RFC 9068
 * access token (typ: at+jwt) addressed to one of the given audiences. This middleware
 * re-parses the bearer token but does not re-verify its signature or revocation status
 * — that is auth:api's job, which MUST run before this middleware in the route's
 * middleware list.
 */
class CheckAudience
{
    public static function using(string ...$audiences): string
    {
        return static::class.':'.implode(',', $audiences);
    }

    public function handle(Request $request, Closure $next, string ...$audiences): Response
    {
        $jwt = $request->bearerToken();

        if ($jwt === null) {
            abort(403);
        }

        try {
            $parsed = (new Parser(new JoseEncoder))->parse($jwt);
        } catch (Throwable) {
            abort(403);
        }

        if (! $parsed instanceof Plain || $parsed->headers()->get('typ') !== 'at+jwt') {
            abort(403);
        }

        $tokenAudiences = $this->normalize($parsed->claims()->get('aud'));

        if (array_intersect($audiences, $tokenAudiences) === []) {
            abort(403);
        }

        return $next($request);
    }

    /** @return string[] */
    private function normalize(mixed $aud): array
    {
        return array_values(array_filter(is_array($aud) ? $aud : [$aud], 'is_string'));
    }
}
