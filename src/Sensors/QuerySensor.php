<?php

namespace Laravel\Nightwatch\Sensors;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Laravel\Nightwatch;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Location;
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
        private RequestState|CommandState $executionState,
        private Clock $clock,
        private Location $location,
    ) {
        //
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     * @return array{0: Nightwatch\Events\Query, 1: (Closure(): Nightwatch\Records\Query)}
     */
    public function __invoke(QueryExecuted $event, array $trace): array
    {
        $durationInMicroseconds = (int) round($event->time * 1000);
        $timestamp = $this->clock->microtime() - ($event->time / 1000);

        return [
            $query = new Nightwatch\Events\Query(
                sql: $event->sql,
                connection: $event->connectionName ?? '', // @phpstan-ignore nullCoalesce.property
            ),
            function () use ($event, $trace, $durationInMicroseconds, $timestamp, $query) {
                [$file, $line] = $this->location->forQueryTrace($trace);

                $this->executionState->queries++;

                return new Nightwatch\Records\Query(
                    timestamp: $timestamp,
                    deploy: $this->executionState->deploy,
                    server: $this->executionState->server,
                    _group: $this->hash($event),
                    trace_id: $this->executionState->trace,
                    execution_source: $this->executionState->source,
                    execution_id: $this->executionState->id(),
                    execution_preview: $this->executionState->executionPreview(),
                    execution_stage: $this->executionState->stage,
                    user: $this->executionState->user->id(),
                    sql: $query->sql,
                    file: $file ?? '',
                    line: $line ?? 0,
                    duration: $durationInMicroseconds,
                    connection: $query->connection,
                );
            },
        ];
    }

    private function hash(QueryExecuted $event): string
    {
        if (! in_array($event->connection->getDriverName(), ['mariadb', 'mysql', 'pgsql', 'sqlite', 'sqlsrv'], true)) {
            return hash('xxh128', "{$event->connectionName},{$event->sql}");
        }

        $sql = preg_replace('/in \([\d?\s,]+\)/', 'in (...?)', $event->sql) ?? $event->sql;

        if (str_contains($sql, 'insert')) {
            $sql = preg_replace('/values [(?,\s)]+/', 'values ...', $sql) ?? $sql;
        }

        return hash('xxh128', "{$event->connectionName},{$sql}");
    }
}
