<?php

namespace Tests\Unit\Hooks;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Laravel\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ExceptionHandlerResolvedHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $exceptionHandler = new class($this->app) extends Handler
        {
            public bool $thrownInReportable = false;

            public function reportable(callable $reportUsing): void
            {
                $this->thrownInReportable = true;

                throw new RuntimeException('Whoops!');
            }
        };

        $handler = new ExceptionHandlerResolvedHandler($this->core);
        $handler($exceptionHandler);

        $this->assertTrue($exceptionHandler->thrownInReportable);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }

    public function test_it_gracefully_handles_custom_exception_handlers(): void
    {
        $exceptions = [];
        $this->core->sensor->exceptionSensor = function ($e) use (&$exceptions): void {
            $exceptions[] = $e;
        };

        $exceptionHandler = new class implements ExceptionHandler
        {
            public function report(Throwable $e): void
            {
                //
            }

            public function shouldReport(Throwable $e): void
            {
                //
            }

            public function render($request, Throwable $e): void
            {
                //
            }

            public function renderForConsole($output, Throwable $e): void
            {
                //
            }
        };

        $handler = new ExceptionHandlerResolvedHandler($this->core);
        $handler($exceptionHandler);
        $exceptionHandler->report(new RuntimeException('Test'));

        $this->assertCount(0, $exceptions);
    }
}
