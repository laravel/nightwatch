<?php

namespace Tests\Unit\Hooks;

use DateTimeImmutable;
use Laravel\Nightwatch\Hooks\LogRecordProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

class LogRecordProcessorTest extends TestCase
{
    public function test_it_gracefully_handles_exceptions(): void
    {
        $record = new class(new DateTimeImmutable, 'single', Level::Debug, 'Hello world') extends LogRecord
        {
            public bool $thrownInWith = false;

            public function with(mixed ...$args): self
            {
                $this->thrownInWith = true;

                throw new RuntimeException('Whoops!');
            }
        };

        $processor = new LogRecordProcessor($this->core, 'Y-m-d H:i:s');
        $processor($record);

        $this->assertTrue($record->thrownInWith);
        $this->assertSame(1, $this->core->executionState->exceptions);
    }
}
