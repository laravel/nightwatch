<?php

namespace Tests\Unit\Hooks;

use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

use function response;

class RouteMiddlewareTest extends TestCase
{
    public function test_it_sets_the_execution_stage(): void
    {
        $this->core->executionState->stage = ExecutionStage::BeforeMiddleware;

        $request = Request::create('/test');
        $next = function ($request) {
            $this->assertSame(ExecutionStage::Action, $this->core->executionState->stage);

            return 'response';
        };

        $middleware = new RouteMiddleware($this->core);
        $response = $middleware->handle($request, $next);

        $this->assertSame('response', $response);
        $this->assertSame(ExecutionStage::AfterMiddleware, $this->core->executionState->stage);
    }

    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInStageSensor = 0;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor++;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $request = Request::create('/test');
        $nextCalledWith = null;
        $next = function ($request) use (&$nextCalledWith) {
            $nextCalledWith = $request;

            return 'response';
        };

        $middleware = new RouteMiddleware($this->core);
        $response = $middleware->handle($request, $next);

        $this->assertSame(2, $thrownInStageSensor);
        $this->assertSame('response', $response);
        $this->assertSame($request, $nextCalledWith);
        $this->assertSame(2, $this->core->executionState->exceptions);
    }

    public function test_it_handles_response_types_that_laravel_does_not_wrap(): void
    {
        $thrownInStageSensor = 0;
        $this->core->sensor->stageSensor = function () use (&$thrownInStageSensor): void {
            $thrownInStageSensor++;

            throw new RuntimeException('Whoops!');
        };
        $this->core->executionState->stage = ExecutionStage::Bootstrap;

        $request = Request::create('/test');
        $nextCalledWith = null;
        $next = function ($request) use (&$nextCalledWith) {
            $nextCalledWith = $request;

            return response()->streamDownload(function (): void {
                echo '...';
            });
        };

        $middleware = new RouteMiddleware($this->core);
        $response = $middleware->handle($request, $next);

        $this->assertSame(2, $thrownInStageSensor);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame($request, $nextCalledWith);
        $this->assertSame(2, $this->core->executionState->exceptions);
    }
}
