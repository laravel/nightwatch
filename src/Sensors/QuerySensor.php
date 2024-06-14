<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\UserProvider;

final class QuerySensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private UserProvider $user,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, category, file, line
     * TODO we need to increase the validation size in lambd and column size in
     * clickhouse now we are collecting microseconds for both `duration` and
     * `queries_duration`.
     */
    public function __invoke(QueryExecuted $event): void
    {
        $durationInMicroseconds = (int) round($event->time * 1000);

        $timestamp = CarbonImmutable::now('UTC')->subMicroseconds($durationInMicroseconds)->toDateTimeString();

        $this->recordsBuffer->writeQuery(new Query(
            timestamp: $timestamp,
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            user: $this->user->id(),
            sql: $event->sql,
            category: 'select',
            file: 'app/Models/User.php',
            line: 5,
            duration: $durationInMicroseconds,
            connection: $event->connectionName,
        ));

        $this->executionParent->queries++;
        $this->executionParent->queries_duration += $durationInMicroseconds;
    }
}
