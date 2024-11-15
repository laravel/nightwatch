<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $handler = new HttpKernelResolvedHandler($sensor);
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle(Request::create('/test'));
    $kernel->terminate(Request::create('/test'), new Response());

    expect($sensor->thrown)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };
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
    $handler = new HttpKernelResolvedHandler($sensor);
    $handler($kernel, app());

    expect(true)->toBeTrue();
});
