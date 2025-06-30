<?php

namespace Tests\Unit\Hooks;

use Carbon\CarbonImmutable;
use Laravel\Nightwatch\Hooks\LogHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

use function in_array;

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

        $handler = new LogHandler($this->core, Level::Debug);
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

    #[DataProvider('logLevelProvider')]
    public function test_it_respects_the_log_level(string $level, array $expected): void
    {
        $records = [
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Debug, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Info, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Notice, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Warning, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Error, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Critical, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Alert, 'hello world'),
            new LogRecord(CarbonImmutable::now(), 'nightwatch', Level::Emergency, 'hello world'),
        ];

        $handler = new LogHandler($this->core, Level::fromName($level));

        foreach ($records as $record) {
            if (in_array($record->level->toPsrLogLevel(), $expected, true)) {
                $this->assertTrue($handler->isHandling($record));
            } else {
                $this->assertFalse($handler->isHandling($record));
            }
        }
    }

    public static function logLevelProvider(): array
    {
        return [
            ['debug', ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']],
            ['info', ['info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']],
            ['notice', ['notice', 'warning', 'error', 'critical', 'alert', 'emergency']],
            ['warning', ['warning', 'error', 'critical', 'alert', 'emergency']],
            ['error', ['error', 'critical', 'alert', 'emergency']],
            ['critical', ['critical', 'alert', 'emergency']],
            ['alert', ['alert', 'emergency']],
            ['emergency', ['emergency']],
        ];
    }
}
