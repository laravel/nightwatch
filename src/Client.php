<?php

namespace Laravel\Nightwatch;

use Laravel\Nightwatch\Contracts\Client as ClientContract;
use React\Http\Browser;
use React\Promise\PromiseInterface;

class Client implements ClientContract
{
    public function __construct(private Browser $browser)
    {
        //
    }

    public function send(string $payload): PromiseInterface
    {
        return $this->browser->post('/', body: $payload);
    }
}
