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
    public function test_it_gracefully_handles_custom_exception_handlers()
    {
        $kernel = new class implements HttpKernel
        {
            public function bootstrap()
            {
                //
            }

            public function handle($request)
            {
                //
            }

            public function terminate($request, $response)
            {
                //
            }

            public function getApplication()
            {
                //
            }
        };

        $handler = new HttpKernelResolvedHandler($this->core);
        $handler($kernel, $this->app);

        // This test passes if an exception is not thrown...
        $this->assertTrue(true);
    }

    public function test_it_gracefully_handles_exceptions_when_registering_lifecycle_handler()
    {
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
            $unrecoverableExceptions[] = $e;
        });

        $kernel = new class($this->app, $this->app[Router::class]) extends Kernel
        {
            public bool $thrownInWhenRequestLifecycleIsLongerThan = false;

            public function whenRequestLifecycleIsLongerThan($threshold, $handler)
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

    public function test_it_gracefully_handles_exceptions_when_prepending_middleware()
    {
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
            $unrecoverableExceptions[] = $e;
        });

        $kernel = new class($this->app, $this->app[Router::class]) extends Kernel
        {
            public bool $thrownInPrependMiddleware = false;

            public function prependMiddleware($middleware)
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

    public function test_it_gracefully_handles_exceptions_when_determining_whether_to_sample_the_request()
    {
        $this->core->config['sampling'] = [];
        $exceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
            $exceptions[] = $e;
        });
        $kernel = $this->app[HttpKernel::class];

        $this->assertTrue($this->core->shouldSample);

        $handler = new HttpKernelResolvedHandler($this->core);
        $handler($kernel, $this->app);

        $this->assertFalse($this->core->shouldSample);
        $this->assertCount(1, $exceptions);
        $this->assertSame('Undefined array key "requests"', $exceptions[0]->getMessage());
    }
}
