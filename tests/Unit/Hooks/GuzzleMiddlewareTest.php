<?php

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use Laravel\Nightwatch\SensorManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('gracefully handles exceptions in the before middleware', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $clock = new class(0) extends Clock
    {
        public $thrown = false;

        public function microtime(): float
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $middleware = new GuzzleMiddleware($sensor, $clock);

    $stack = $middleware(static function () {});
    $stack(new Request('GET', '/test'), []);

    $this->assertTrue($clock->thrown);
});

it('gracefully handles exceptions in the after middleware', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $middleware = new GuzzleMiddleware($sensor, app(Clock::class));
    $stack = $middleware(fn () => new FulfilledPromise(new Response()));

    $stack(new Request('GET', '/test'), [])->wait();

    $this->assertTrue($sensor->thrown);
});
