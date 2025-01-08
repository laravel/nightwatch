<?php

use Carbon\CarbonImmutable;
use Laravel\Nightwatch\Hooks\LogHandler;
use Laravel\Nightwatch\SensorManager;
use Monolog\Level;
use Monolog\LogRecord;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function log(LogRecord $record): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $handler = new LogHandler($nightwatch);

    $handler->handle(new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Debug, 'hello world'));

    expect($sensor->thrown)->toBeTrue();
});
