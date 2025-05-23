<?php

namespace Tests\Unit\Hooks;

use Carbon\CarbonImmutable;
use Laravel\Nightwatch\Hooks\LogHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

class LogHandlerTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $thrownInLogSensor = false;
        $this->core->sensor->logSensor = function () use (&$thrownInLogSensor): void {
            $thrownInLogSensor = true;

            throw new RuntimeException('Whoops!');
        };
        $record = new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Debug, 'hello world');

        $handler = new LogHandler($this->core);
        $handler->handle($record);

        $this->assertTrue($thrownInLogSensor);
        $this->assertSame(1, $this->core->executionState->exceptions);

        $thrownInLogSensor = false;
        $handler->handleBatch([null]);

        $this->assertFalse($thrownInLogSensor);
        $this->assertSame(2, $this->core->executionState->exceptions);

        $this->assertNull($handler->close());
        $this->assertFalse($thrownInLogSensor);
        $this->assertSame(2, $this->core->executionState->exceptions);

        $this->assertTrue($handler->isHandling($record));
        $this->assertFalse($thrownInLogSensor);
        $this->assertSame(2, $this->core->executionState->exceptions);
    }
}
