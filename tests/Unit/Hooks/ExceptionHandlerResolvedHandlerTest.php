<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;

it('gracefully handles exceptions', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $thrownInExceptionSensor = false;
    nightwatch()->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor) {
        $thrownInExceptionSensor = true;

        throw new RuntimeException('Whoops!');
    };

    $exceptionHandler = app(ExceptionHandler::class);
    $handler = new ExceptionHandlerResolvedHandler(nightwatch());
    $handler($exceptionHandler);

    $exceptionHandler->report(new RuntimeException('Test'));

    expect($thrownInExceptionSensor)->toBeTrue();
});

it('gracefully handles custom exception handlers', function () {
    $exceptions = [];
    nightwatch()->sensor->exceptionSensor = function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    };

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

    $handler = new ExceptionHandlerResolvedHandler(nightwatch());
    $handler($exceptionHandler);
    $exceptionHandler->report(new RuntimeException('Test'));

    expect($exceptions)->toHaveCount(0);
});
