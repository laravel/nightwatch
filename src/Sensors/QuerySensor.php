<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Query;

final class QuerySensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        $now = CarbonImmutable::now('UTC');
        $duration = (int) $event->time; // TODO we want to capture this at a higher resolution

        $this->recordsBuffer->writeQuery(new Query(
            timestamp: $now->subMilliseconds($duration)->toDateTimeString(),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''), // TODO
            trace_id: $this->traceId,
            execution_context: 'request', // TODO
            execution_id: '00000000-0000-0000-0000-000000000000', // TODO
            user: Auth::id() ?? '', // TODO allow this to be customised
            sql: $event->sql,
            category: 'select', // TODO
            file: 'app/Models/User.php', // TODO
            line: 5, // TODO
            duration: $duration, // TODO we need to increase the validation size in lambda now we are collecting microseconds
            connection: $event->connectionName,
        ));

        $this->executionParent->queries++;
        $this->executionParent->queries_duration += $duration; // TODO we need to increase the validation size in lambda now we are collecting microseconds
    }
}
