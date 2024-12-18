<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Log;
use Laravel\Nightwatch\UserProvider;
use Monolog\LogRecord;

/**
 * @internal
 */
final class LogSensor
{
    public function __construct(
        private ExecutionState $executionState,
        private UserProvider $user,
    ) {
        //
    }

    public function __invoke(LogRecord $record): void
    {
        $this->executionState->logs++;

        $this->executionState->records->write(new Log(
            timestamp: (float) $record->datetime->format('U.u'),
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            level: $record->level->toPsrLogLevel(),
            message: $record->message,
            context: $record->context,
            extra: $record->extra,
        ));
    }
}
