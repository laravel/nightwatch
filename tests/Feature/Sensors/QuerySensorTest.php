<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use MongoDB\Laravel\Connection as MongoDbConnection;

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
});

it('can ingest queries', function () {
    ignoreMigrationQueries();
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
    ignoreMigrationQueries();
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
    ignoreMigrationQueries();
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
    ignoreMigrationQueries();
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

test('group hash collapses variadic "where in" binding placeholders and raw integer values', function ($sql, $expected, $connection) {
    $ingest = fakeIngest();
    Route::get('/users', function () use ($sql, $connection) {
        Event::dispatch(new QueryExecuted($sql, [], 1, $connection));
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('query:0.sql', $sql);
    $ingest->assertLatestWrite('query:0._group', $expected);
})->with([
    'mysql' => [
        'select * from `users` where `users`.`id` in (1, 2, 3) and `id` in (?, ?, ?)',
        hash('md5', 'foo,select * from `users` where `users`.`id` in (...?) and `id` in (...?)'),
        new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
    ],
    'mariadb' => [
        'select * from `users` where `users`.`id` in (1, 2, 3) and `id` in (?, ?, ?)',
        hash('md5', 'foo,select * from `users` where `users`.`id` in (...?) and `id` in (...?)'),
        new MariaDbConnection('test', config: ['name' => 'foo', 'driver' => 'mariadb']),
    ],
    'pgsql' => [
        'select * from "users" where "users"."id" in (1, 2, 3) and "id" in (?, ?, ?)',
        hash('md5', 'foo,select * from "users" where "users"."id" in (...?) and "id" in (...?)'),
        new PostgresConnection('test', config: ['name' => 'foo', 'driver' => 'pgsql']),
    ],
    'sqlite' => [
        'select * from "users" where "users"."id" in (1, 2, 3) and "id" in (?, ?, ?)',
        hash('md5', 'foo,select * from "users" where "users"."id" in (...?) and "id" in (...?)'),
        new SQLiteConnection('test', config: ['name' => 'foo', 'driver' => 'sqlite']),
    ],
    'sqlsrv' => [
        'select * from [users] where [users].[id] in (1, 2, 3) and [id] in (?, ?, ?)',
        hash('md5', 'foo,select * from [users] where [users].[id] in (...?) and [id] in (...?)'),
        new SqlServerConnection('test', config: ['name' => 'foo', 'driver' => 'sqlsrv']),
    ],
    'mongodb' => [
        'some mongo query in (1, 2, 3) and [id] in (?, ?, ?)',
        hash('md5', 'foo,some mongo query in (1, 2, 3) and [id] in (?, ?, ?)'),
        new MongoDbConnection(['name' => 'foo', 'driver' => 'mongodb', 'host' => 'localhost', 'database' => 'test']),
    ],
]);

test('group hash collapses insert rows', function ($sql, $expected, $connection) {
    $ingest = fakeIngest();
    Route::get('/users', function () use ($sql, $connection) {
        Event::dispatch(new QueryExecuted($sql, [], 1, $connection));
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('query:0.sql', $sql);
    $ingest->assertLatestWrite('query:0._group', $expected);
})->with([
    'mysql one row' => [
        'insert into `users` (`id`, `name`) values (?, ?)',
        hash('md5', 'foo,insert into `users` (`id`, `name`) values ...'),
        new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
    ],
    'mysql multiple rows' => [
        'insert into `users` (`id`, `name`) values (?, ?), (?, ?)',
        hash('md5', 'foo,insert into `users` (`id`, `name`) values ...'),
        new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
    ],
    'mysql trailing stuff' => [
        'insert into `users` (`id`, `name`) values (?, ?), (?, ?) on duplicate key update `name` = ?',
        hash('md5', 'foo,insert into `users` (`id`, `name`) values ...on duplicate key update `name` = ?'),
        new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
    ],
    'mariadb' => [
        'insert into `users` (`id`, `name`) values (?, ?), (?, ?)',
        hash('md5', 'foo,insert into `users` (`id`, `name`) values ...'),
        new MariaDbConnection('test', config: ['name' => 'foo', 'driver' => 'mariadb']),
    ],
    'pgsql' => [
        'insert into "users" ("id", "name") values (?, ?), (?, ?)',
        hash('md5', 'foo,insert into "users" ("id", "name") values ...'),
        new PostgresConnection('test', config: ['name' => 'foo', 'driver' => 'pgsql']),
    ],
    'sqlite' => [
        'insert into "users" ("id", "name") values (?, ?), (?, ?)',
        hash('md5', 'foo,insert into "users" ("id", "name") values ...'),
        new SQLiteConnection('test', config: ['name' => 'foo', 'driver' => 'sqlite']),
    ],
    'sqlsrv' => [
        'insert into [users] ([id], [name]) values (?, ?), (?, ?)',
        hash('md5', 'foo,insert into [users] ([id], [name]) values ...'),
        new SqlServerConnection('test', config: ['name' => 'foo', 'driver' => 'sqlsrv']),
    ],
    'mongodb' => [
        'insert some mongo query values (?, ?), (?, ?)',
        hash('md5', 'foo,insert some mongo query values (?, ?), (?, ?)'),
        new MongoDbConnection(['name' => 'foo', 'driver' => 'mongodb', 'host' => 'localhost', 'database' => 'test']),
    ],
]);
