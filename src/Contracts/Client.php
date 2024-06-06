<?php

namespace Laravel\Nightwatch\Contracts;

use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

interface Client
{
    /**
     * @return PromiseInterface<ResponseInterface>
     */
    public function send(string $payload): PromiseInterface;
}
