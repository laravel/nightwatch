<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Throwable;

/**
 * @internal
 */
final class LogHandler implements HandlerInterface
{
    /**
     * @param  Core<RequestState>|Core<CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        try {
            $this->nightwatch->sensor->log($record);
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
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
            $this->nightwatch->report($e);
        }
    }

    public function close(): void
    {
        //
    }
}
