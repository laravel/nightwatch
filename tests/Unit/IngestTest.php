<?php

use Laravel\Nightwatch\Contracts\Client;
use Laravel\Nightwatch\Ingest;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

it('limits the number of concurrent requests', function () {
    $browser = new FakeBrowser(fn () => new Promise(fn () => new Response

    });
    $ingest = new Ingest();
    //
})->todo();

it('sends request and returns result with duration', function () {
    //
})->todo();
