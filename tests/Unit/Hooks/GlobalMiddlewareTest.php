<?php

namespace Tests\Unit\Hooks;

use Illuminate\Http\Request;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

use function response;

class GlobalMiddlewareTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions_when_capturing_execution_preview(): void
    {
        $request = new class extends Request
        {
            public bool $thrownInGetMethod = false;

            public function getMethod(): string
            {
                $this->thrownInGetMethod = true;

                throw new RuntimeException('Whoops!');
            }
        };
        $next = fn () => response('response');

        $middleware = new GlobalMiddleware($this->core);
        $response = $middleware->handle($request, $next);

        $this->assertTrue($request->thrownInGetMethod);
        $this->assertSame(1, $this->core->executionState->exceptions);
        $this->assertSame('response', $response->content());
    }

    public function test_it_gracefully_handles_exceptions_when_the_terminating_event_doesnt_exist(): void
    {
        Compatibility::$terminatingEventExists = false;
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $middleware = new GlobalMiddleware($this->core);
        $request = Request::create('/test');
        $nextCalledWith = null;
        $next = function ($request) use (&$nextCalledWith) {
            $nextCalledWith = $request;

            return response('response');
        };

        $response = $middleware->handle($request, $next);

        $this->assertFalse($thrownInStageSensor);
        $this->assertSame('response', $response->content());
        $this->assertSame($request, $nextCalledWith);

        $middleware->terminate($request, $response);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }

    public function test_it_handles_response_types_that_laravel_does_not_wrap(): void
    {
        Compatibility::$terminatingEventExists = false;
        $thrownInStageSensor = false;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $middleware = new GlobalMiddleware($this->core);
        $request = Request::create('/test');
        $nextCalledWith = null;
        $next = function ($request) use (&$nextCalledWith) {
            $nextCalledWith = $request;

            return response()->streamDownload(function (): void {
                echo '...';
            });
        };

        $response = $middleware->handle($request, $next);

        $this->assertFalse($thrownInStageSensor);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame($request, $nextCalledWith);

        $middleware->terminate($request, $response);

        $this->assertTrue($thrownInStageSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
