<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;

it('gracefully handles exceptions', function () {
    $exceptionHandler = new class(app()) extends Handler
    {
        public bool $thrownInReportable = false;

        public function reportable(callable $reportUsing)
        {
            $this->thrownInReportable = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $handler = new ExceptionHandlerResolvedHandler(nightwatch());
    $handler($exceptionHandler);

    expect($exceptionHandler->thrownInReportable)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    forgetRecordedExceptions(1);
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
