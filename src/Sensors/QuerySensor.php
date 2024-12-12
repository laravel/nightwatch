<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\ExecutionState;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\UserProvider;

use function hash;
use function preg_replace;
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

        $sql = match ($event->connection->getDriverName()) {
            'mariadb',
            'mysql',
            'pgsql',
            'sqlite',
            'sqlsrv' => preg_replace('/in \([\d?\s,]+\)/', 'in (...?)', $event->sql) ?? $event->sql,
            default => $event->sql,
        };

        $this->executionState->records->write(new Query(
            timestamp: $this->clock->microtime() - ($event->time / 1000),
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: hash('md5', "{$event->connectionName},{$sql}"),
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->user->id(),
            sql: $sql,
            file: $file ?? '',
            line: $line ?? 0,
            duration: $durationInMicroseconds,
            connection: $event->connectionName,
        ));
    }
}
