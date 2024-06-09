<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\RecordCollection;
use Laravel\Nightwatch\TraceId;

final class QuerySensor
{
    public function __construct(
        private RecordCollection $records,
        private Config $config,
        private TraceId $traceId,
        private AuthManager $auth,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        $now = CarbonImmutable::now();

        $this->records['queries'][] = [
            'timestamp' => $now->subMilliseconds($event->time)->format('Y-m-d H:i:s'), // Can I do this without Carbon?
            'deploy_id' => $this->config->get('nightwatch.deploy_id'),
            'server' => $this->config->get('nightwatch.server'),
            'group' => hash('sha256', ''), // TODO
            'trace_id' => $this->traceId->value(),
            'execution_context' => 'request', // TODO
            'execution_id' => '00000000-0000-0000-0000-000000000000', // TODO
            'user' => Auth::id() ?? '', // TODO allow this to be customised
            'sql' => $event->sql,
            'category' => 'select', // TODO
            'file' => 'app/Models/User.php', // TODO
            'line' => 5, // TODO
            'duration' => $event->time,
            'connection' => $event->connectionName,
        ];

        $executionParent = $this->records['execution_parent'];

        $executionParent['queries'] += 1;
        $executionParent['queries_duration'] += $event->time;
    }
}
