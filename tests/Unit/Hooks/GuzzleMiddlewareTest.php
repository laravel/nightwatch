<?php

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use Laravel\Nightwatch\SensorManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('gracefully handles exceptions in the before middleware', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
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
    $clock = app(Clock::class);
    $clock->microtimeResolver = function () use (&$thrown): float {
        $thrown = true;

        throw new RuntimeException('Whoops!');
    };

    $middleware = new GuzzleMiddleware($nightwatch, $clock);

    $stack = $middleware(fn () => new Response(body: 'ok'));
    $response = $stack(new Request('GET', '/test'), []);

    $this->assertTrue($thrown);
    $this->assertSame('ok', (string) $response->getBody());
});

it('gracefully handles exceptions in the after middleware', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $middleware = new GuzzleMiddleware($nightwatch, app(Clock::class));
    $stack = $middleware(fn () => new FulfilledPromise(new Response(body: 'ok')));

    $response = $stack(new Request('GET', '/test'), [])->wait();

    $this->assertTrue($sensor->thrown);
    $this->assertSame('ok', (string) $response->getBody());
});
