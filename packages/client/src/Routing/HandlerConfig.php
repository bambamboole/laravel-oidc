<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidcClient\Routing;

/**
 * The resolved configuration for a single {@see Handler}: where it lives, what
 * handles it, and the middleware it runs through.
 */
final readonly class HandlerConfig
{
    /**
     * @param  string|array{0: class-string, 1: string}  $controller  An invokable controller class, or a [class, method] pair.
     * @param  array<int, string>  $middleware
     */
    public function __construct(
        public string $route,
        public string|array $controller,
        public array $middleware,
    ) {}
}
