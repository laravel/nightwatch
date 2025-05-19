<?php

namespace Tests\Unit\Hooks;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Nightwatch\Hooks\GuzzleMiddleware;
use RuntimeException;
use Tests\TestCase;

class GuzzleMiddlewareTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions_in_the_before_middleware(): void
    {
        $exceptions = [];
        $this->core->sensor->exceptionSensor = function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        };
        $thrownInMicrotimeResolver = false;
        $this->core->clock->microtimeResolver = function () use (&$thrownInMicrotimeResolver): float {
            $thrownInMicrotimeResolver = true;

            throw new RuntimeException('Whoops!');
        };

        $middleware = new GuzzleMiddleware($this->core);

        $stack = $middleware(fn () => new FulfilledPromise(new Response(body: 'ok')));
        $response = $stack(new Request('GET', '/test'), [])->wait();

        $this->assertTrue($thrownInMicrotimeResolver);
        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());
        $this->assertSame('ok', (string) $response->getBody());
    }

    public function test_it_gracefully_handles_exceptions_in_the_after_middleware(): void
    {
        $thrownInOutgoingRequestSensor = false;
        $this->core->sensor->outgoingRequestSensor = function () use (&$thrownInOutgoingRequestSensor): void {
            $thrownInOutgoingRequestSensor = true;

            throw new RuntimeException('Whoops!');
        };

        $middleware = new GuzzleMiddleware($this->core);
        $stack = $middleware(fn () => new FulfilledPromise(new Response(body: 'ok')));

        $response = $stack(new Request('GET', '/test'), [])->wait();

        $this->assertTrue($thrownInOutgoingRequestSensor);
        $this->assertSame('ok', (string) $response->getBody());
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
