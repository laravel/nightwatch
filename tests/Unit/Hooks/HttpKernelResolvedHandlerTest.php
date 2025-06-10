<?php

namespace Tests\Unit\Hooks;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use RuntimeException;
use Tests\TestCase;

class HttpKernelResolvedHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_gracefully_handles_custom_exception_handlers(): void
    {
        $kernel = new class implements HttpKernel
        {
            public function bootstrap(): void
            {
                //
            }

            public function handle($request): void
            {
                //
            }

            public function terminate($request, $response): void
            {
                //
            }

            public function getApplication(): void
            {
                //
            }
        };

        $handler = new HttpKernelResolvedHandler($this->core);
        $handler($kernel, $this->app);

        // This test passes if an exception is not thrown...
        $this->assertTrue(true);
    }

    public function test_it_gracefully_handles_exceptions_when_registering_lifecycle_handler(): void
    {
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions): void {
            $unrecoverableExceptions[] = $e;
        });

        $kernel = new class($this->app, $this->app[Router::class]) extends Kernel
        {
            public bool $thrownInWhenRequestLifecycleIsLongerThan = false;

            public function whenRequestLifecycleIsLongerThan($threshold, $handler): void
            {
                $this->thrownInWhenRequestLifecycleIsLongerThan = true;

                throw new RuntimeException('Whoops!');
            }
        };

        $handler = new HttpKernelResolvedHandler($this->core);
        $handler($kernel, $this->app);

        $this->assertTrue($kernel->thrownInWhenRequestLifecycleIsLongerThan);
        $this->assertCount(1, $unrecoverableExceptions);
    }

    public function test_it_gracefully_handles_exceptions_when_prepending_middleware(): void
    {
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions): void {
            $unrecoverableExceptions[] = $e;
        });

        $kernel = new class($this->app, $this->app[Router::class]) extends Kernel
        {
            public bool $thrownInPrependMiddleware = false;

            public function prependMiddleware($middleware): void
            {
                $this->thrownInPrependMiddleware = true;

                throw new RuntimeException('Whoops!');
            }
        };

        $handler = new HttpKernelResolvedHandler($this->core);
        $handler($kernel, $this->app);

        $this->assertTrue($kernel->thrownInPrependMiddleware);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }

    public function test_it_gracefully_handles_exceptions_when_determining_whether_to_sample_the_request(): void
    {
        $this->core->config['sampling'] = [];
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        });

        $this->assertTrue($this->core->shouldSample);
        $this->app[HttpKernel::class];

        $this->assertFalse($this->core->shouldSample);
        $this->assertCount(1, $exceptions);
        $this->assertSame('Undefined array key "requests"', $exceptions[0]->getMessage());
    }
}
