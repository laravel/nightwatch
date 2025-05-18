<?php

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;

it('gracefully handles custom exception handlers', function () {
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

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $handler($kernel, app());

    // This test passes if an exception is not thrown...
    $this->assertTrue(true);
});

it('gracefully handles exceptions when registering lifecycle handler', function () {
    $unrecoverableExceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
        $unrecoverableExceptions[] = $e;
    });

    $kernel = new class(app(), app(Router::class)) extends Kernel
    {
        public bool $thrownInWhenRequestLifecycleIsLongerThan = false;

        public function whenRequestLifecycleIsLongerThan($threshold, $handler)
        {
            $this->thrownInWhenRequestLifecycleIsLongerThan = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $handler($kernel, app());

    $this->assertTrue($kernel->thrownInWhenRequestLifecycleIsLongerThan);
    $this->assertCount(1, $unrecoverableExceptions);
});

it('gracefully handles exceptions when prepending middleware', function () {
    $unrecoverableExceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions) {
        $unrecoverableExceptions[] = $e;
    });

    $kernel = new class(app(), app(Router::class)) extends Kernel
    {
        public bool $thrownInPrependMiddleware = false;

        public function prependMiddleware($middleware)
        {
            $this->thrownInPrependMiddleware = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $handler($kernel, app());

    $this->assertTrue($kernel->thrownInPrependMiddleware);
    $this->assertSame(1, nightwatch()->executionState->exceptions);
});

it('gracefully handles exceptions when determining whether to sample the request', function () {
    nightwatch()->config['sampling'] = [];
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $kernel = app(HttpKernel::class);

    $this->assertTrue(nightwatch()->shouldSample);

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $handler($kernel, app());

    $this->assertFalse(nightwatch()->shouldSample);
    $this->assertCount(1, $exceptions);
    $this->assertSame('Undefined array key "requests"', $exceptions[0]->getMessage());
});
