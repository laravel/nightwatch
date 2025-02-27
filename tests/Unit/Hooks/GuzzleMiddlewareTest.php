<?php

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use Laravel\Nightwatch\SensorManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('gracefully handles exceptions in the before middleware', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $thrown = false;
    nightwatch()->clock->microtimeResolver = function () use (&$thrown): float {
        $thrown = true;

        throw new RuntimeException('Whoops!');
    };

    $middleware = new GuzzleMiddleware($nightwatch);

    $stack = $middleware(fn () => new Response(body: 'ok'));
    $response = $stack(new Request('GET', '/test'), []);

    $this->assertTrue($thrown);
    $this->assertSame('ok', (string) $response->getBody());
});

it('gracefully handles exceptions in the after middleware', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $middleware = new GuzzleMiddleware($nightwatch);
    $stack = $middleware(fn () => new FulfilledPromise(new Response(body: 'ok')));

    $response = $stack(new Request('GET', '/test'), [])->wait();

    $this->assertTrue($sensor->thrown);
    $this->assertSame('ok', (string) $response->getBody());
});
