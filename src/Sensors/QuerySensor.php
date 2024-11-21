<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\UserProvider;

use function hash;
use function round;

/**
 * @internal
 */
final class QuerySensor
{
    public function __construct(
        private Clock $clock,
        private ExecutionState $executionState,
        private Location $location,
        private RecordsBuffer $recordsBuffer,
        private UserProvider $user,
    ) {
        //
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     */
    public function __invoke(QueryExecuted $event, array $trace): void
    {
        $durationInMicroseconds = (int) round($event->time * 1000);
        [$file, $line] = $this->location->forQueryTrace($trace);

        $this->executionState->queries++;

        $this->recordsBuffer->write(new Query(
            timestamp: $this->clock->microtime() - ($event->time / 1000),
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$event->connectionName},{$event->sql}"),
            trace_id: $this->executionState->trace,
            execution_context: $this->executionState->context,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            sql: $event->sql,
            file: $file ?? '',
            line: $line ?? 0,
            duration: $durationInMicroseconds,
            connection: $event->connectionName,
        ));
    }
}
