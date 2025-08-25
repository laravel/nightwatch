<?php

namespace Tests\Unit\Hooks;

use DateTimeImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Hooks\LogRecordProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

use function now;

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

    public function test_it_does_not_impact_other_handlers_in_the_stack(): void
    {
        $this->travelTo(Date::parse('2000-01-01 00:00:00'));
        $streams = $this->fakeTcpStreams();
        Config::set([
            'logging.channels.stack.channels' => ['log-stream', 'nightwatch'],
            'logging.channels.log-stream' => [
                'driver' => 'monolog',
                'handler' => \Monolog\Handler\StreamHandler::class,
                'handler_with' => [
                    'stream' => 'tcp://log-stream',
                ],
            ],
        ]);

        Log::channel('stack')->info('test', [
            'now' => now('Australia/Melbourne'),
        ]);
        $this->core->digest();

        $this->assertCount(2, $streams);
        [$log, $ingest] = $streams;

        $this->assertStringContainsString('{"now":"2000-01-01 11:00:00"}', $log->value);
        $this->assertStringContainsString('{\"now\":\"2000-01-01 00:00:00.000000+00:00\"}', $ingest->value);
    }

    public function test_it_preserves_timezone_for_record(): void
    {
        $this->travelTo(Date::parse('2000-01-01 00:00:00'));
        $streams = $this->fakeTcpStreams();
        Config::set([
            'logging.channels.stack.channels' => ['log-stream', 'nightwatch'],
            'logging.channels.log-stream' => [
                'driver' => 'monolog',
                'handler' => \Monolog\Handler\StreamHandler::class,
                'handler_with' => [
                    'stream' => 'tcp://log-stream',
                ],
            ],
        ]);

        Log::channel('stack')->info('test', [
            'now' => now('Australia/Melbourne'),
        ]);
        $this->core->digest();

        $this->assertCount(2, $streams);
        [$log, $ingest] = $streams;

        $this->assertStringContainsString('{"now":"2000-01-01 11:00:00"}', $log->value);
        $this->assertStringContainsString('{\"now\":\"2000-01-01 00:00:00.000000+00:00\"}', $ingest->value);
    }
}
