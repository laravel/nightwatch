<?php

namespace Tests;

use RuntimeException;

use function gzencode;
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

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, string>  $headers
     */
    public static function ingest(
        array $records,
        ?string $url = null,
        ?array $headers = null,
    ): self {
        $body = gzencode(json_encode(['records' => $records], flags: JSON_THROW_ON_ERROR), 5);

        if ($body === false) {
            throw new RuntimeException('Unable to compress body.');
        }

        return new self(
            $url ?? 'https://ingest.nightwatch.laravel.com',
            $headers ?? ['authorization' => 'Bearer NIGHTWATCH_TEST_TOKEN'],
            $body,
        );
    }
}
