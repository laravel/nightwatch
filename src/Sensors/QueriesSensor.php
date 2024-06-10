<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\RecordCollection;

final class QueriesSensor
{
    public function __construct(
        private RecordCollection $records,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        $now = CarbonImmutable::now();

        $this->records['queries'][] = [
            // TODO Can I do this without Carbon?
            // TODO `time` is a float. Does this correctly adjust?
            'timestamp' => $now->subMilliseconds($event->time)->format('Y-m-d H:i:s'),
            'deploy_id' => $this->deployId,
            'server' => $this->server,
            'group' => hash('sha256', ''), // TODO
            'trace_id' => $this->traceId,
            'execution_context' => 'request', // TODO
            'execution_id' => '00000000-0000-0000-0000-000000000000', // TODO
            'user' => Auth::id() ?? '', // TODO allow this to be customised
            'sql' => $event->sql,
            'category' => 'select', // TODO
            'file' => 'app/Models/User.php', // TODO
            'line' => 5, // TODO
            // TODO this is done to support the validation. Do we want to track
            // microseconds instead of milliseconds, though?
            'duration' => (int) $event->time,
            'connection' => $event->connectionName,
        ];

        $executionParent = $this->records['execution_parent'];

        $executionParent['queries'] += 1;
        // TODO this is done to support the validation. Do we want to track
        // microseconds instead of milliseconds, though?
        $executionParent['queries_duration'] += (int) $event->time;
    }
}
