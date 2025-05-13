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

    expect(true)->toBe(true);
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

    expect($kernel->thrownInWhenRequestLifecycleIsLongerThan)->toBeTrue();
    expect($unrecoverableExceptions)->toHaveCount(1);
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

    expect($kernel->thrownInPrependMiddleware)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});

it('gracefully handles exceptions when determining whether to sample the request', function () {
    nightwatch()->sampling = [];
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $kernel = app(HttpKernel::class);

    expect(nightwatch()->shouldSample)->toBeTrue();

    $handler = new HttpKernelResolvedHandler(nightwatch());
    $handler($kernel, app());

    expect(nightwatch()->shouldSample)->toBeFalse();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Undefined array key "requests"');
});
