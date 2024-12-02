<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

defineEnvironment(function () {
    forceRequestExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    ignoreMigrationQueries();
});

it('can ingest queries', function () {
    $ingest = fakeIngest();
    afterMigrations(fn () => prependListener(QueryExecuted::class, function ($event) {
        $event->time = 4.321;

        travelTo(now()->addMicroseconds(4321));
    }));

    $line = null;
    Route::get('/users', function () use (&$line) {
        $line = __LINE__ + 2;

        return DB::table('users')->get();
    });

    $response = get('/users');

    // Workbench replaces `testing` with `sqlite`. Will capture it dynamically
    // so that the tests pass whether workbench has configured its own database
    // or not.
    expect($connection = config('database.default'))->toBeIn(['testing', 'sqlite']);

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('query:*', [
        [
            'v' => 1,
            't' => 'query',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('md5', $connection.',select * from "users"'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'sql' => 'select * from "users"',
            // We have temporarily disabled debug_backtrace to reduce the memory impact
            // 'file' => 'tests/Feature/Sensors/QuerySensorTest.php',
            // 'line' => $line,
            'file' => '',
            'line' => 0,
            'duration' => 4321,
            'connection' => $connection,
        ],
    ]);
});

it('can captures the line and file', function () {
    $ingest = fakeIngest();

    $line = null;
    Route::get('/users', function () use (&$line) {
        $line = __LINE__ + 2;

        return DB::table('users')->get();
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('query:0.file', 'tests/Feature/Sensors/QuerySensorTest.php');
    $ingest->assertLatestWrite('query:0.line', $line);
})->skip('We have temporarily disabled debug_backtrace to reduce the memory impact');

it('captures aggregate query data on the request', function () {
    $ingest = fakeIngest();
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        $event->time = 4.321;

        travelTo(now()->addMicroseconds(4321));
    });
    Route::get('/users', function () {
        DB::table('users')->get();
        DB::table('users')->get();

        return [];
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.queries', 2);
});

it('always uses current time minus execution time for the timestamp', function () {
    $ingest = fakeIngest();
    afterMigrations(fn () => prependListener(QueryExecuted::class, function (QueryExecuted $event) {
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
    $ingest->assertLatestWrite('query:0.timestamp', 946688523.466665);
});
