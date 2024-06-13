<?php

namespace Laravel\Nightwatch;

use React\Http\Browser;
use React\Promise\PromiseInterface;

final class Client
{
    public function __construct(private Browser $browser)
    {
        //
    }

    public function send(string $payload): PromiseInterface
    {
        return $this->browser->post('/nightwatch-ingest', body: $payload);
    }
}
