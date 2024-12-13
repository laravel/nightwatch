<?php

namespace Laravel\Nightwatch\Sensors;

use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;

use function hash;
use function in_array;
use function preg_replace;
use function round;
use function str_contains;

/**
 * @internal
 */
final class QuerySensor
{
    public function __construct(
        private Clock $clock,
        private RequestState|CommandState $executionState,
        private Location $location,
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

        $this->executionState->records->write(new Query(
            timestamp: $this->clock->microtime() - ($event->time / 1000),
            deploy: $this->executionState->deploy,
            server: $this->executionState->server,
            _group: $this->hash($event),
            trace_id: $this->executionState->trace,
            execution_source: $this->executionState->source,
            execution_id: $this->executionState->id,
            execution_stage: $this->executionState->stage,
            user: $this->executionState->user->id(),
            sql: $event->sql,
            file: $file ?? '',
            line: $line ?? 0,
            duration: $durationInMicroseconds,
            connection: $event->connectionName,
        ));
    }

    protected function hash(QueryExecuted $event): string
    {
        if (! in_array($event->connection->getDriverName(), ['mariadb', 'mysql', 'pgsql', 'sqlite', 'sqlsrv'], true)) {
            return hash('md5', "{$event->connectionName},{$event->sql}");
        }

        $sql = preg_replace('/in \([\d?\s,]+\)/', 'in (...?)', $event->sql) ?? $event->sql;

        if (str_contains($sql, 'insert')) {
            $sql = preg_replace('/values [(?,\s\n)]+/i', 'values ...', $sql) ?? $sql;
        }

        return hash('md5', "{$event->connectionName},{$sql}");
    }
}
