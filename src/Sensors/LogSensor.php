<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use Monolog\LogRecord;

use function json_encode;

/**
 * @internal
 */
final class LogSensor
{
    public function __construct(
        private RequestState|CommandState $executionState,
    ) {
        //
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(LogRecord $record): array
    {
        $this->executionState->logs++;

        return [
            'v' => 1,
            't' => 'log',
            'timestamp' => (float) $record->datetime->format('U.u'),
            'deploy' => $this->executionState->deploy,
            'server' => $this->executionState->server,
            'trace_id' => $this->executionState->trace,
            'execution_source' => $this->executionState->source,
            'execution_id' => $this->executionState->id(),
            'execution_preview' => $this->executionState->executionPreview(),
            'execution_stage' => $this->executionState->stage,
            'user' => $this->executionState->user->id(),
            // --- //
            'level' => Str::tinyText($record->level->toPsrLogLevel()),
            'message' => Str::text($record->message),
            'context' => Str::text(json_encode((object) $record->context, flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
            'extra' => Str::text(json_encode((object) $record->extra, flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
        ];
    }
}
