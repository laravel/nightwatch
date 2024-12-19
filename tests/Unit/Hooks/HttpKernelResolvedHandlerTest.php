<?php

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\HttpFoundation\Response;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function () {
    forceRequestExecutionState();
});

it('gracefully handles exceptions in all three phases', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
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
    $state = app(RequestState::class);
    $state->records = new class extends RecordsBuffer
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
    expect($state->records->thrownInFlush)->toBeTrue();
});

it('gracefully handles exceptions thrown while ingesting', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
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
    });
    $ingest = app()->instance(LocalIngest::class, new class implements LocalIngest
    {
        public bool $thrown = false;

        public function write(string $payload): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $handler = new HttpKernelResolvedHandler($nightwatch);
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle(Request::create('/test'));
    $kernel->terminate(Request::create('/test'), new Response);

    expect($ingest->thrown)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
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
