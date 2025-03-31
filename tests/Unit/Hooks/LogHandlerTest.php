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

    expect($thrownInLogSensor)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);

    $thrownInLogSensor = false;
    $handler->handleBatch([null]);

    expect($thrownInLogSensor)->toBeFalse();
    expect(nightwatch()->state->exceptions)->toBe(2);

    expect($handler->close())->toBeNull();
    expect($thrownInLogSensor)->toBeFalse();
    expect(nightwatch()->state->exceptions)->toBe(2);

    expect($handler->isHandling($record))->toBeTrue();
    expect($thrownInLogSensor)->toBeFalse();
    expect(nightwatch()->state->exceptions)->toBe(2);

    forgetRecordedExceptions(2);
});
