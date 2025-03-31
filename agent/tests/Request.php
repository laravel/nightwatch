<?php

namespace Tests;

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
}
