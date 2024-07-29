<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\ExecutionPhase;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\UserProvider;

/**
 * @internal
 */
final class QuerySensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private UserProvider $user,
        private Clock $clock,
        private Location $location,
        private string $deploy,
        private string $server,
        private string $traceId,
        private string $executionId,
        private string $executionContext,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, category
     *
     * @param  list<array{ file?: string, line?: int }>  $trace
     */
    public function __invoke(QueryExecuted $event, array $trace, ExecutionPhase $executionPhase): void
    {
        $durationInMicroseconds = (int) round($event->time * 1000);
        [$file, $line] = $this->location->forQueryTrace($trace);

        $this->executionParent->queries++;
        $this->executionParent->queries_duration += $durationInMicroseconds;

        $this->recordsBuffer->writeQuery(new Query(
            timestamp: $this->clock->microtime() - ($event->time / 1000),
            deploy: $this->deploy,
            server: $this->server,
            group: hash('md5', $event->sql),
            trace_id: $this->traceId,
            execution_context: $this->executionContext,
            execution_id: $this->executionId,
            execution_phase: $executionPhase,
            user: $this->user->id(),
            sql: $event->sql,
            file: $file ?? '',
            line: $line ?? 0,
            duration: $durationInMicroseconds,
            connection: $event->connectionName,
        ));
    }
}
