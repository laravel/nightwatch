<?php

namespace Laravel\Nightwatch\Hooks;

use DateTimeZone;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * @internal
 */
final class LogHandler implements HandlerInterface
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     * @param  array<ProcessorInterface>  $processors
     */
    public function __construct(
        private Core $nightwatch,
        private Level $level,
        private array $processors,
    ) {
        //
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->nightwatch->shouldCaptureLogs() && $this->level->includes($record->level);
    }

    public function handle(LogRecord $record): bool
    {
        try {
            if (! $this->isHandling($record)) {
                return false;
            }

            // When used in a log stack, it is possible that we lose our
            // previously configured timezone passed to the parent monolog
            // instance and have it replaced with the system's default
            // timezone.  We do a final check here to ensure we are always
            // working with UTC.
            if ($record->datetime->getTimezone()->getName() !== 'UTC') {
                $record = $record->with(
                    datetime: $record->datetime->setTimezone(new DateTimeZone('UTC'))
                );
            }

            foreach ($this->processors as $processor) {
                $record = $processor($record);
            }

            $this->nightwatch->log($record);

            return true;
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);

            return false;
        }
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
            $this->nightwatch->report($e, handled: true);
        }
    }

    public function close(): void
    {
        //
    }
}
