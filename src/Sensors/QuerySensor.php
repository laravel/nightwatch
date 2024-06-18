<?php

namespace Laravel\Nightwatch\Sensors;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Buffers\RecordsBuffer;
use Laravel\Nightwatch\Contracts\Clock;
use Laravel\Nightwatch\Records\ExecutionParent;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\UserProvider;

final class QuerySensor
{
    public function __construct(
        private RecordsBuffer $recordsBuffer,
        private ExecutionParent $executionParent,
        private UserProvider $user,
        private Clock $clock,
        private string $deployId,
        private string $server,
        private string $traceId,
    ) {
        //
    }

    /**
     * TODO group, execution_context, execution_id, category, file, line
     */
    public function __invoke(QueryExecuted $event): void
    {
        $nowMicrotime = $this->clock->microtime();

        $startMicrotime = $nowMicrotime - ($event->time / 1000);

        $duration = (int) round($event->time * 1000);

        $this->recordsBuffer->writeQuery(new Query(
            timestamp: DateTimeImmutable::createFromFormat('U', (int) $startMicrotime, new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            deploy_id: $this->deployId,
            server: $this->server,
            group: hash('sha256', ''),
            trace_id: $this->traceId,
            execution_context: 'request',
            execution_id: '00000000-0000-0000-0000-000000000000',
            execution_offset: $this->clock->executionOffset($startMicrotime),
            user: $this->user->id(),
            sql: $event->sql,
            category: 'select',
            file: 'app/Models/User.php',
            line: 5,
            duration: $duration,
            connection: $event->connectionName,
        ));

        $this->executionParent->queries++;
        $this->executionParent->queries_duration += $duration;
    }
}
