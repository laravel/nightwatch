<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\SensorManager;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Throwable;

/**
 * @internal
 */
final class LogHandler implements HandlerInterface
{
    public function __construct(private SensorManager $sensor)
    {
        //
    }

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        try {
            $this->sensor->log($record);
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }

        return true;
    }

    /**
     * @param  list<LogRecord>  $records
     */
    public function handleBatch(array $records): void
    {
        try {
            foreach ($records as $record) {
                $this->handle($record);
            }
        } catch (Throwable $e) {
            $this->sensor->exception($e);
        }
    }

    public function close(): void
    {
        //
    }
}
