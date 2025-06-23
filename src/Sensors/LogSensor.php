<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\Contracts\Ingest;
use Laravel\Nightwatch\Records\Log;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Monolog\LogRecord;

use function json_encode;

/**
 * @internal
 */
final class LogSensor
{
    public function __construct(
        private Ingest $ingest,
        private RequestState|CommandState $executionState,
    ) {
        //
    }

    public function __invoke(LogRecord $record): void
    {
        $this->executionState->logs++;

        $this->ingest->write(new Log(
            timestamp: (float) $record->datetime->format('U.u'),
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id(),
            execution_preview: $this->executionState->executionPreview(),
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            level: $record->level->toPsrLogLevel(),
            message: $record->message,
            context: json_encode((object) $record->context, flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
            extra: json_encode((object) $record->extra, flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
        ));
    }
}
