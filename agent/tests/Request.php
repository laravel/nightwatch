<?php

namespace Tests;

use function json_encode;

class Request
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $url,
        public array $headers = [],
        public string $body = '',
    ) {
        //
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<mixed>  $body
     */
    public static function json(
        string $url,
        array $headers = [],
        array $body = [],
    ): self {
        return new self($url, $headers, json_encode((object) $body, flags: JSON_THROW_ON_ERROR));
    }
}
