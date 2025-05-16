<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use MongoDB\Laravel\Connection as MongoDbConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

use function class_exists;
use function dirname;
use function expect;
use function hash;
use function now;

class QuerySensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
        // --- //
        $this->app->setBasePath($base = dirname($this->app->basePath()));
        $this->core->sensor->location->setBasePath($base);
        $this->core->sensor->location->setPublicPath($base.'/public');
    }

    public function test_it_can_ingest_queries()
    {
        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function ($event) {
            $event->time = 4.321;

            $this->travelTo(now()->addMicroseconds(4321));
        });

        $line = null;
        Route::get('/users', function () use (&$line) {
            $line = __LINE__ + 2;

            return DB::table('users')->get();
        });

        $response = $this->get('/users');

        // Workbench replaces `testing` with `sqlite`. Will capture it dynamically
        // so that the tests pass whether workbench has configured its own database
        // or not.
        expect($connection = Config::get('database.default'))->toBeIn(['testing', 'sqlite']);

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:*', [
            [
                'v' => 1,
                't' => 'query',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', $connection.',select * from "users"'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'GET /users',
                'execution_stage' => 'action',
                'user' => '',
                'sql' => 'select * from "users"',
                'file' => 'tests/Feature/Sensors/QuerySensorTest.php',
                'line' => $line,
                'duration' => 4321,
                'connection' => $connection,
            ],
        ]);
    }

    public function test_it_can_captures_the_line_and_file()
    {
        $ingest = $this->fakeIngest();

        $line = null;
        Route::get('/users', function () use (&$line) {
            $line = __LINE__ + 2;

            return DB::table('users')->get();
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:0.file', 'tests/Feature/Sensors/QuerySensorTest.php');
        $ingest->assertLatestWrite('query:0.line', $line);
    }

    public function test_it_captures_aggregate_query_data_on_the_request()
    {
        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            $event->time = 4.321;

            $this->travelTo(now()->addMicroseconds(4321));
        });
        Route::get('/users', function () {
            DB::table('users')->get();
            DB::table('users')->get();

            return [];
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.queries', 2);
    }

    public function test_it_always_uses_current_time_minus_execution_time_for_the_timestamp()
    {
        $ingest = $this->fakeIngest();
        $this->prependListener(QueryExecuted::class, function (QueryExecuted $event) {
            $event->time = 4.321;

            $this->travelTo(now()->addMicroseconds(4321));
        });
        Route::get('/users', function () {
            $this->travelTo(now()->addMicroseconds(9876));

            return DB::table('users')->get();
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:0.timestamp', 946688523.466665);
    }

    #[DataProvider('whereInQueries')]
    public function test_group_hash_collapses_variadic_where_in_binding_placeholders_and_raw_integer_values(string $sql, string $expected, Connection $connection)
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () use ($sql, $connection) {
            Event::dispatch(new QueryExecuted($sql, [], 1, $connection));
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:0.sql', $sql);
        $ingest->assertLatestWrite('query:0._group', $expected);
    }

    public static function whereInQueries(): iterable
    {
        yield 'mysql' => [
            'select * from `users` where `users`.`id` in (1, 2, 3) and `id` in (?, ?, ?)',
            hash('xxh128', 'foo,select * from `users` where `users`.`id` in (...?) and `id` in (...?)'),
            new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
        ];

        if (class_exists(MariaDbConnection::class)) {
            yield 'mariadb' => [
                'select * from `users` where `users`.`id` in (1, 2, 3) and `id` in (?, ?, ?)',
                hash('xxh128', 'foo,select * from `users` where `users`.`id` in (...?) and `id` in (...?)'),
                new MariaDbConnection('test', config: ['name' => 'foo', 'driver' => 'mariadb']),
            ];
        }

        yield 'pgsql' => [
            'select * from "users" where "users"."id" in (1, 2, 3) and "id" in (?, ?, ?)',
            hash('xxh128', 'foo,select * from "users" where "users"."id" in (...?) and "id" in (...?)'),
            new PostgresConnection('test', config: ['name' => 'foo', 'driver' => 'pgsql']),
        ];

        yield 'sqlite' => [
            'select * from "users" where "users"."id" in (1, 2, 3) and "id" in (?, ?, ?)',
            hash('xxh128', 'foo,select * from "users" where "users"."id" in (...?) and "id" in (...?)'),
            new SQLiteConnection('test', config: ['name' => 'foo', 'driver' => 'sqlite']),
        ];

        yield 'sqlsrv' => [
            'select * from [users] where [users].[id] in (1, 2, 3) and [id] in (?, ?, ?)',
            hash('xxh128', 'foo,select * from [users] where [users].[id] in (...?) and [id] in (...?)'),
            new SqlServerConnection('test', config: ['name' => 'foo', 'driver' => 'sqlsrv']),
        ];

        yield 'mongodb' => [
            'some mongo query in (1, 2, 3) and [id] in (?, ?, ?)',
            hash('xxh128', 'foo,some mongo query in (1, 2, 3) and [id] in (?, ?, ?)'),
            new MongoDbConnection(['name' => 'foo', 'driver' => 'mongodb', 'host' => 'localhost', 'database' => 'test']),
        ];
    }

    #[DataProvider('insertQueries')]
    public function test_group_hash_collapses_insert_rows(string $sql, string $expected, Connection $connection)
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () use ($sql, $connection) {
            Event::dispatch(new QueryExecuted($sql, [], 1, $connection));
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:0.sql', $sql);
        $ingest->assertLatestWrite('query:0._group', $expected);
    }

    public static function insertQueries(): iterable
    {
        yield 'mysql one row' => [
            'insert into `users` (`id`, `name`) values (?, ?)',
            hash('xxh128', 'foo,insert into `users` (`id`, `name`) values ...'),
            new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
        ];

        yield 'mysql multiple rows' => [
            'insert into `users` (`id`, `name`) values (?, ?), (?, ?)',
            hash('xxh128', 'foo,insert into `users` (`id`, `name`) values ...'),
            new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
        ];

        yield 'mysql trailing stuff' => [
            'insert into `users` (`id`, `name`) values (?, ?), (?, ?) on duplicate key update `name` = ?',
            hash('xxh128', 'foo,insert into `users` (`id`, `name`) values ...on duplicate key update `name` = ?'),
            new MySqlConnection('test', config: ['name' => 'foo', 'driver' => 'mysql']),
        ];

        if (class_exists(MariaDbConnection::class)) {
            yield 'mariadb' => [
                'insert into `users` (`id`, `name`) values (?, ?), (?, ?)',
                hash('xxh128', 'foo,insert into `users` (`id`, `name`) values ...'),
                new MariaDbConnection('test', config: ['name' => 'foo', 'driver' => 'mariadb']),
            ];
        }

        yield 'pgsql' => [
            'insert into "users" ("id", "name") values (?, ?), (?, ?)',
            hash('xxh128', 'foo,insert into "users" ("id", "name") values ...'),
            new PostgresConnection('test', config: ['name' => 'foo', 'driver' => 'pgsql']),
        ];

        yield 'sqlite' => [
            'insert into "users" ("id", "name") values (?, ?), (?, ?)',
            hash('xxh128', 'foo,insert into "users" ("id", "name") values ...'),
            new SQLiteConnection('test', config: ['name' => 'foo', 'driver' => 'sqlite']),
        ];

        yield 'sqlsrv' => [
            'insert into [users] ([id], [name]) values (?, ?), (?, ?)',
            hash('xxh128', 'foo,insert into [users] ([id], [name]) values ...'),
            new SqlServerConnection('test', config: ['name' => 'foo', 'driver' => 'sqlsrv']),
        ];

        yield 'mongodb' => [
            'insert some mongo query values (?, ?), (?, ?)',
            hash('xxh128', 'foo,insert some mongo query values (?, ?), (?, ?)'),
            new MongoDbConnection(['name' => 'foo', 'driver' => 'mongodb', 'host' => 'localhost', 'database' => 'test']),
        ];
    }
}
