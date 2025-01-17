<?php

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Response;

beforeAll(function () {
    forceRequestExecutionState();
});

it('gracefully handles exceptions in all three phases', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
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
    });
    $nightwatch->state->records = new class extends RecordsBuffer
    {
        public $thrownInFlush = false;

        public function __construct() {}

        public function flush(): string
        {
            $this->thrownInFlush = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $handler = new HttpKernelResolvedHandler($nightwatch);
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle(Request::create('/test'));
    $kernel->terminate(Request::create('/test'), new Response);

    expect($sensor->thrownInStage)->toBeTrue();
    expect($sensor->thrownInRequest)->toBeTrue();
    expect($nightwatch->state->records->thrownInFlush)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
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
    $handler = new HttpKernelResolvedHandler($nightwatch);
    $handler($kernel, app());

    expect(true)->toBeTrue();
});
