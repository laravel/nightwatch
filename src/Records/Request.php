<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class Request
{
    /**
     * @param  list<string>  $route_methods
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly string $routeName,
        public readonly array $routeMethods,
        public readonly string $routeDomain,
        public readonly string $routePath,
        public readonly string $routeAction,
        public readonly string $ip,
        public readonly int $duration,
        public readonly int $statusCode,
        public readonly int $requestSize,
        public readonly int $responseSize,
    ) {}
}
