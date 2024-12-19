<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Support\Facades\Log;
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
            // TODO we need to ensure this does not log to the Nightwatch logger!
            Log::critical('[nightwatch] '.$e->getMessage());
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
            // TODO we need to ensure this does not log to the Nightwatch logger!
            Log::critical('[nightwatch] '.$e->getMessage());
        }
    }

    public function close(): void
    {
        //
    }
}
