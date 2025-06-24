<?php

namespace Laravel\Nightwatch\Hooks;

use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Throwable;

/**
 * @internal
 */
final class LogHandler implements HandlerInterface
{
    protected Level $level;

    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
        private array $config,
    ) {
        //
    }

    public function isHandling(LogRecord $record): bool
    {
        $this->level ??= Logger::toMonologLevel($this->config['level'] ?? 'debug');

        return $this->nightwatch->shouldCaptureLogs() && $this->level->includes($record->level);
    }

    public function handle(LogRecord $record): bool
    {
        try {
            if (! $this->isHandling($record)) {
                return false;
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
