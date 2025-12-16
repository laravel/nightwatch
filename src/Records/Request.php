<?php

namespace Laravel\Nightwatch\Records;

use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;

final class Request
{
    /**
     * @param  array<string>  $routeMethods
     * @param  InputBag<string|int|float|bool|null>  $payload
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
        public HeaderBag $headers,
        public InputBag $payload,
        public FileBag $files,
    ) {
        //
    }
}
