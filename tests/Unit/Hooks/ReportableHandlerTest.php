<?php

namespace Tests\Unit\Hooks;

use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\ReportableHandler;
use RuntimeException;
use Tests\TestCase;

class ReportableHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $unrecoverableExceptions = [];
        Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$unrecoverableExceptions): void {
            $unrecoverableExceptions[] = $e;
        });
        $thrownInExceptionSensor = false;
        $this->core->sensor->exceptionSensor = function () use (&$thrownInExceptionSensor): void {
            $thrownInExceptionSensor = true;

            throw new RuntimeException('Whoops sensor!');
        };

        $exception = new RuntimeException('Whoops app!');

        $handler = new ReportableHandler($this->core);
        $handler($exception);

        $this->assertTrue($thrownInExceptionSensor);
        $this->assertCount(1, $unrecoverableExceptions);
        $this->assertSame('Whoops sensor!', $unrecoverableExceptions[0]->getMessage());
    }
}
