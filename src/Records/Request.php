<?php

namespace Laravel\Nightwatch\Records;

final class Request
{
    /**
     * @param  array<string>  $routeMethods
     */
    public function __construct(
        public readonly string $method,
        public string $url,
        public readonly string $routeName,
        public readonly array $routeMethods,
        public readonly string $routeDomain,
        public readonly string $routePath,
        public readonly string $routeAction,
        public string $ip,
        public readonly int $duration,
        public readonly int $statusCode,
        public readonly int $requestSize,
        public readonly int $responseSize,
    ) {
        //
    }
}
