<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;

it('gracefully handles exceptions in all three phases', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrownInStage = false;
        public bool $thrownInRequest = false;
        public bool $thrownInFlush = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrownInStage = true;

            throw new RuntimeException('Whoops!');
        }

        public function request(Request $request, Response $response): void
        {
            $this->thrownInRequest = true;

            throw new RuntimeException('Whoops!');
        }

        public function flush(): string
        {
            $this->thrownInFlush = true;

            throw new RuntimeException('Whoops!');
        }
    };
    // $ingest = app()->instance(LocalIngest::class, new class implements LocalIngest {
    //     public bool $thrown = false;

    //     public function write(string $payload): void
    //     {
    //         $this->thrown = true;

    //         throw new RuntimeException('Whoops!');
    //     }
    // });
    $handler = new HttpKernelResolvedHandler($sensor);
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle(Request::create('/test'));
    $kernel->terminate(Request::create('/test'), new Response());

    expect($sensor->thrownInStage)->toBeTrue();
    expect($sensor->thrownInRequest)->toBeTrue();
    expect($sensor->thrownInFlush)->toBeTrue();
});

it('gracefully handles exceptions thrown while ingesting', function () {
    $sensor = new class extends SensorManager
    {
        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            //
        }

        public function request(Request $request, Response $response): void
        {
            //
        }

        public function flush(): string
        {
            return '';
        }
    };
    $ingest = app()->instance(LocalIngest::class, new class implements LocalIngest {
        public bool $thrown = false;

        public function write(string $payload): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $handler = new HttpKernelResolvedHandler($sensor);
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle(Request::create('/test'));
    $kernel->terminate(Request::create('/test'), new Response());

    expect($ingest->thrown)->toBeTrue();
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
