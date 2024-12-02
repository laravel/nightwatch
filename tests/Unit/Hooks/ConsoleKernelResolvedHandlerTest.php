<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Support\Env;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\LocalIngest;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\Hooks\ConsoleKernelResolvedHandler;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\HttpFoundation\Response;

use function Orchestra\Testbench\Pest\defineEnvironment;

defineEnvironment(function () {
    forceCommandExecutionState();
});

it('gracefully handles exceptions in all three phases', function () {
    Artisan::command('app:build', fn () => 0);
    $sensor = new class extends SensorManager
    {
        public bool $thrownInStage = false;
        public bool $thrownInCommand = false;
        public bool $thrownInFlush = false;

        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            $this->thrownInStage = true;

            throw new RuntimeException('Whoops!');
        }

        public function command(InputInterface $input, int $status): void
        {
            $this->thrownInCommand = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $state = app(CommandState::class);
    $state->records = new class extends RecordsBuffer {
        public $thrownInFlush = false;
        public function __construct() {}

            public function flush(): string
            {
                $this->thrownInFlush = true;

                throw new RuntimeException('Whoops!');
            }
    };
    $handler = new ConsoleKernelResolvedHandler($sensor, app(CommandState::class));
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle($input = new StringInput('app:build'));
    $kernel->terminate($input, 0);

    expect($sensor->thrownInStage)->toBeTrue();
    expect($sensor->thrownInCommand)->toBeTrue();
    expect($state->records->thrownInFlush)->toBeTrue();
});

it('gracefully handles exceptions thrown while ingesting', function () {
    Artisan::command('app:build', fn () => 0);
    $sensor = new class extends SensorManager
    {
        public function __construct() {}

        public function stage(ExecutionStage $executionStage): void
        {
            //
        }

        public function command(InputInterface $input, int $status): void
        {
            $this->thrownInCommand = true;

            throw new RuntimeException('Whoops!');
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
    $handler = new ConsoleKernelResolvedHandler($sensor, app(CommandState::class));
    $kernel = app(Kernel::class);

    $handler($kernel, app());
    $kernel->handle($input = new StringInput('app:build'));
    $kernel->terminate($input, 0);

    expect($ingest->thrown)->toBeTrue();
});

it('gracefully handles custom Kernel implementations', function () {
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
    $kernel = new class implements ConsoleKernel
    {
        public function bootstrap()
        {
            //
        }

        public function handle($input, $output = null)
        {
            return 0;
        }

        public function call($command, array $parameters = [], $outputBuffer = null)
        {

        }

        public function terminate($input, $status)
        {
            //
        }

        public function queue($command, array $parameters = [])
        {
            //
        }

        public function all()
        {
            return [];
        }

        public function output()
        {
            return '';
        }
    };
    $handler = new ConsoleKernelResolvedHandler($sensor, app(CommandState::class));
    $handler($kernel, app());

    expect(true)->toBeTrue();
});

