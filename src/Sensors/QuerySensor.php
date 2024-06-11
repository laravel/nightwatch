<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\Records;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Query;

final class QuerySensor
{
    public function __construct(
        private Records $records,
        private ExecutionParent $executionParent,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        $now = CarbonImmutable::now();

        $duration = (int) $event->time;

        $this->records->addQuery(new Query(
            // TODO Can I do this without Carbon?
            // TODO `time` is a float. Does this correctly adjust?
            timestamp: $now->subMilliseconds($event->time)->format('Y-m-d H:i:s'),
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
            // TODO this is done to support the validation. Do we want to track
            // microseconds instead of milliseconds, though?
            duration: $duration,
            connection: $event->connectionName,
        ));

        $this->executionParent->queries++;
        // TODO this is done to support the validation. Do we want to track
        // microseconds instead of milliseconds, though?
        $this->executionParent->queries_duration += $duration;
    }
}
