<?php

use Laravel\Nightwatch\Hooks\LogRecordProcessor;
use Monolog\Level;
use Monolog\LogRecord;

it('gracefully handles exceptions', function () {
    $record = new class(new DateTimeImmutable, 'single', Level::Debug, 'Hello world') extends LogRecord
    {
        public bool $thrown = false;

        public function with(mixed ...$args): self
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $processor = new LogRecordProcessor(nightwatch(), 'Y-m-d H:i:s');
    $processor($record);

    expect($record->thrown)->toBeTrue();
    expect(nightwatch()->state->exceptions)->toBe(1);
});
