<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function exception(Throwable $e): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $state = new CommandState(
        timestamp: microtime(true),
        trace: (string) Str::uuid(),
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: new Clock,
    );

    $exceptionHandler = app(ExceptionHandler::class);
    $handler = new ExceptionHandlerResolvedHandler($sensor, $state);
    $handler($exceptionHandler);
    $exceptionHandler->report(new RuntimeException('Test'));

    expect($sensor->thrown)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $sensor = new class extends SensorManager
    {
        public bool $captured = false;

        public function __construct() {}

        public function exception(Throwable $e): void
        {
            $this->captured = true;
        }
    };
    $state = new CommandState(
        timestamp: microtime(true),
        trace: (string) Str::uuid(),
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: new Clock,
    );
    $exceptionHandler = new class implements ExceptionHandler
    {
        public function report(Throwable $e)
        {
            //
        }

        public function shouldReport(Throwable $e)
        {
            //
        }

        public function render($request, Throwable $e)
        {
            //
        }

        public function renderForConsole($output, Throwable $e)
        {
            //
        }
    };
    $handler = new ExceptionHandlerResolvedHandler($sensor, $state);
    $handler($exceptionHandler);
    $exceptionHandler->report(new RuntimeException('Test'));

    expect($sensor->captured)->toBeFalse();
});
