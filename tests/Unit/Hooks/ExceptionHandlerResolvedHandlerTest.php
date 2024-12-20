<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function exception(Throwable $e): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $exceptionHandler = app(ExceptionHandler::class);
    $handler = new ExceptionHandlerResolvedHandler($nightwatch);
    $handler($exceptionHandler);
    $exceptionHandler->report(new RuntimeException('Test'));

    expect($sensor->thrown)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $captured = false;

        public function __construct() {}

        public function exception(Throwable $e): void
        {
            $this->captured = true;
        }
    });
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
    $handler = new ExceptionHandlerResolvedHandler($nightwatch);
    $handler($exceptionHandler);
    $exceptionHandler->report(new RuntimeException('Test'));

    expect($sensor->captured)->toBeFalse();
});
