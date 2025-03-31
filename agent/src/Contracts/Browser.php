<?php

namespace Laravel\NightwatchAgent\Contracts;

use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

interface Browser
{
    /**
     * @param  array<string, string>  $headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function post(string $url, array $headers, string $body): PromiseInterface;
}
