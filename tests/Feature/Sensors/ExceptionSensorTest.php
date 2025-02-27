<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Types\Str;
use Spatie\LaravelIgnition\IgnitionServiceProvider;

use function Pest\Laravel\get;

beforeAll(function () {
    forceRequestExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    app()->setBasePath($base = dirname(app()->basePath()));
    nightwatch()->sensor->location->setBasePath($base);
    nightwatch()->sensor->location->setPublicPath($base.'/public');

    setPhpVersion('8.4.1');
    setLaravelVersion('11.33.0');
    Config::set('app.debug', false);
    ini_set('zend.exception_ignore_args', '0');
});

it('can ingest thrown exceptions', function () {
    $ingest = fakeIngest();
    $trace = null;
    $line = null;
    Route::get('/users', function () use (&$trace, &$line) {
        $line = __LINE__ + 1;
        $e = new MyException('Whoops!');

        $trace = $e->getTrace();

        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:*', [
        [
            'v' => 1,
            't' => 'exception',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', "MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'class' => 'MyException',
            'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
            'line' => $line,
            'message' => 'Whoops!',
            'code' => '0',
            'trace' => json_encode(array_map(fn ($frame) => [
                'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {
                    'object' => $arg::class,
                    'string' => 'string',
                    'array' => 'array',
                }, $frame['args'])).')',
            ], $trace)),
            'handled' => false,
            'php_version' => '8.4.1',
            'laravel_version' => '11.33.0',
        ],
    ]);
});

it('captures the code', function () {
    $ingest = fakeIngest();
    $line = null;
    Route::get('/users', function () use (&$line) {
        $line = __LINE__ + 1;
        throw new MyException('Whoops!', 999);
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0._group', hash('xxh128', "MyException,999,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"));
    $ingest->assertLatestWrite('exception:0.code', '999');
});

it('can ingest reported exceptions', function () {
    $ingest = fakeIngest();
    $trace = null;
    $line = null;
    Route::get('/users', function () use (&$trace, &$line) {
        $line = __LINE__ + 1;
        $e = new MyException('Whoops!');

        $trace = $e->getTrace();

        report($e);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:*', [
        [
            'v' => 1,
            't' => 'exception',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', "MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'class' => 'MyException',
            'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
            'line' => $line,
            'message' => 'Whoops!',
            'code' => '0',
            'trace' => json_encode(array_map(fn ($frame) => [
                'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {
                    'object' => $arg::class,
                    'string' => 'string',
                    'array' => 'array',
                }, $frame['args'])).')',
            ], $trace)),
            'handled' => true,
            'php_version' => '8.4.1',
            'laravel_version' => '11.33.0',
        ],
    ]);
});

it('captures aggregate exception data on the request', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        report(new RuntimeException('Whoops!'));
        report(new RuntimeException('Whoops!'));
        throw new RuntimeException('Whoops!');
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.exceptions', 3);
});

it('handles view exceptions', function () {
    expect(App::providerIsLoaded(IgnitionServiceProvider::class))->toBe(false);

    $ingest = fakeIngest();
    Route::view('exception', 'exception');

    $response = get('exception');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.line', 0);
    $ingest->assertLatestWrite('exception:0.file', 'workbench/resources/views/exception.blade.php');
    $ingest->assertLatestWrite('exception:0.class', 'Exception');
    $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
    $ingest->assertLatestWrite('exception:0.code', '999');
    $ingest->assertLatestWrite('exception:0._group', hash('xxh128', 'Exception,999,workbench/resources/views/exception.blade.php,'));
});

it('handles spatie view exceptions', function () {
    App::register(IgnitionServiceProvider::class);
    expect(App::providerIsLoaded(IgnitionServiceProvider::class))->toBe(true);

    $ingest = fakeIngest();
    Route::view('exception', 'exception');

    $response = get('exception');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.line', 6);
    $ingest->assertLatestWrite('exception:0.file', 'workbench/resources/views/exception.blade.php');
    $ingest->assertLatestWrite('exception:0.class', 'Exception');
    $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
    $ingest->assertLatestWrite('exception:0.code', '999');
    $ingest->assertLatestWrite('exception:0._group', hash('xxh128', 'Exception,999,workbench/resources/views/exception.blade.php,6'));
});

it('handles unknown lines for internal locations', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'file' => base_path('app/Models/User.php'),
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.file', 'app/Models/User.php');
    $ingest->assertLatestWrite('exception:0.line', 0);
});

it('captures handled and unhandled exceptions', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    Route::get('/users', function () use ($e) {
        report($e);

        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.handled', true);
    $ingest->assertLatestWrite('exception:1.handled', false);
});

it('handles the file in the trace', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            //
        ],
        [
            'file' => 5,
        ],
        [
            'file' => 'the/file.php',
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', json_encode([
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[unknown file]',
            'source' => '()',
        ],
        [
            'file' => 'the/file.php',
            'source' => '()',
        ],
    ]));
});

it('handles the line in the trace', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            //
        ],
        [
            'line' => 'x',
        ],
        [
            'line' => 5,
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', json_encode([
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]:5',
            'source' => '()',
        ],
    ]));
});

it('handles the class in the trace', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            //
        ],
        [
            'class' => 5,
        ],
        [
            'class' => 'TheClass',
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', json_encode([
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => 'TheClass()',
        ],
    ]));
});

it('handles the function in the trace', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            //
        ],
        [
            'function' => 5,
        ],
        [
            'function' => 'the_function',
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', json_encode([
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => 'the_function()',
        ],
    ]));
});

it('handles the args in the trace', function () {
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            //
        ],
        [
            'args' => 5,
        ],
        [
            'args' => [],
        ],
        [
            'args' => [
                null,
                true,
                99,
                9.9,
                'hello world',
                [],
                new stdClass,
                MyEnum::MyCase,
                fn () => null,
                $resourceToClose = fopen(__FILE__, 'r'),
                tap(fopen(__FILE__, 'r'), fn ($r) => fclose($r)),
            ],
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', json_encode([
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => '()',
        ],
        [
            'file' => '[internal function]',
            'source' => '(null, bool, int, float, string, array, stdClass, MyEnum, Closure, resource, resource (closed))',
        ],
    ]));

    fclose($resourceToClose);
});

it('handles named arguments for variadic functions', function () {
    $args = [];
    try {
        (fn (...$args) => throw new Exception('Whoops!'))(foo: 1, bar: 2);
    } catch (Throwable $e) {
        $args = $e->getTrace()[0]['args'];
    }
    $ingest = fakeIngest();
    $e = new Exception('Whoops!');
    $reflectedException = new ReflectionClass($e);
    $reflectedException->getProperty('trace')->setValue($e, [
        [
            'args' => $args,
        ],
    ]);
    Route::get('/users', function () use ($e) {
        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', json_encode([
        [
            'file' => '[internal function]',
            'source' => '(foo: int, bar: int)',
        ],
    ]));
});

it('handles ini setting disabling args in exceptions', function () {
    $ingest = fakeIngest();
    Route::get('/users', function (Request $request) {
        throw new RuntimeException;
    });

    ini_set('zend.exception_ignore_args', '1');
    $response = get('/users');
    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => ! str_contains($trace, '{closure}(Illuminate\\\\Http\\\\Request)'));

    ini_set('zend.exception_ignore_args', '0');
    $response = get('/users');
    $response->assertServerError();
    $ingest->assertWrittenTimes(2);
    $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => str_contains($trace, '{closure}(Illuminate\\\\Http\\\\Request)'));
});

it('strips base_path from trace files', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        throw new RuntimeException;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => str_contains($trace, '"file":"vendor\/laravel\/framework\/src\/Illuminate\/Routing\/Route.php:'));
});

it('can manually report exceptions', function () {
    $ingest = fakeIngest();
    $trace = null;
    $line = null;
    Route::get('/users', function () use (&$trace, &$line) {
        $line = __LINE__ + 1;
        $e = new MyException('Whoops!');

        $trace = $e->getTrace();

        Nightwatch::report($e);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:*', [
        [
            'v' => 1,
            't' => 'exception',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', "MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_source' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'class' => 'MyException',
            'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
            'line' => $line,
            'message' => 'Whoops!',
            'code' => '0',
            'trace' => json_encode(array_map(fn ($frame) => [
                'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {
                    'object' => $arg::class,
                    'string' => 'string',
                    'array' => 'array',
                }, $frame['args'])).')',
            ], $trace)),
            'handled' => false,
            'php_version' => '8.4.1',
            'laravel_version' => '11.33.0',
        ],
    ]);
});

it('handles PDOExceptions where the code is a string', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () use (&$trace, &$line) {
        DB::table('__foo__')->get();
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exception:0.code', 'HY000');
});

final class MyException extends RuntimeException
{
    public function render()
    {
        return response('', 500);
    }
}

enum MyEnum
{
    case MyCase;
}
