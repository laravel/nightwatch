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
    public function test_it_gracefully_handles_exceptions()
    {
        $exceptionHandler = new class($this->app) extends Handler
        {
            public bool $thrownInReportable = false;

            public function reportable(callable $reportUsing)
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

    public function test_it_gracefully_handles_custom_exception_handlers()
    {
        $exceptions = [];
        $this->core->sensor->exceptionSensor = function ($e) use (&$exceptions) {
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

        $handler = new ExceptionHandlerResolvedHandler($this->core);
        $handler($exceptionHandler);
        $exceptionHandler->report(new RuntimeException('Test'));

        $this->assertCount(0, $exceptions);
    }
}
