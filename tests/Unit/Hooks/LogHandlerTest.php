<?php

use Carbon\CarbonImmutable;
use Laravel\Nightwatch\Hooks\LogHandler;
use Monolog\Level;
use Monolog\LogRecord;

it('gracefully handles exceptions', function () {
    $thrownInLogSensor = false;
    nightwatch()->sensor->logSensor = function () use (&$thrownInLogSensor) {
        $thrownInLogSensor = true;

        throw new RuntimeException('Whoops!');
    };
    $record = new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Debug, 'hello world');

    $handler = new LogHandler(nightwatch());
    $handler->handle($record);

    $this->assertTrue($thrownInLogSensor);
    $this->assertSame(1, nightwatch()->executionState->exceptions);

    $thrownInLogSensor = false;
    $handler->handleBatch([null]);

    $this->assertFalse($thrownInLogSensor);
    $this->assertSame(2, nightwatch()->executionState->exceptions);

    $this->assertNull($handler->close());
    $this->assertFalse($thrownInLogSensor);
    $this->assertSame(2, nightwatch()->executionState->exceptions);

    $this->assertTrue($handler->isHandling($record));
    $this->assertFalse($thrownInLogSensor);
    $this->assertSame(2, nightwatch()->executionState->exceptions);
});
