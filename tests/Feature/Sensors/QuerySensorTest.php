<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function () use (&$ignore) {
    App::setBasePath(realpath(__DIR__.'/../../../'));

    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    ignoreMigrationQueries();
});

function afterMigrations(Closure $callback)
{
    return function (...$args) use ($callback) {
        if (RefreshDatabaseState::$migrated) {
            return $callback(...$args);
        }
    };
}

it('can ingest queries', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, afterMigrations(function (QueryExecuted $event) {
        $event->time = 4.321;

        travelTo(now()->addMicroseconds(4321));
    }));
    $line = null;
    Route::get('/users', function () use (&$line) {
        $line = __LINE__ + 1;

        return DB::table('users')->get();
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queries', [
        [
            'v' => 1,
            'timestamp' => 946688523456789,
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => hash('md5', 'select * from "users"'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000000',
            'user' => '',
            'sql' => 'select * from "users"',
            'category' => 'select',
            'file' => 'tests/Feature/Sensors/QuerySensorTest.php',
            'line' => $line,
            'duration' => 4321,
            'connection' => 'testing',
        ],
    ]);
});

it('always uses current time minus execution time for the timestamp', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, afterMigrations(function (QueryExecuted $event) {
        $event->time = 4.321;

        travelTo(now()->addMicroseconds(4321));
    }));
    Route::get('/users', function () use (&$line) {
        travelTo(now()->addMicroseconds(9876));

        return DB::table('users')->get();
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('queries.0.timestamp', 946688523466665);

});

it('captures aggregate query data on the request', function () {
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 4.321;

        travelTo(now()->addMicroseconds(4321));
    });
    $ingest = fakeIngest();
    Route::get('/users', function () {
        DB::table('users')->get();
        DB::table('users')->get();

        return [];
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.queries', 2);
    $ingest->assertLatestWrite('requests.0.queries_duration', 8642);
});
