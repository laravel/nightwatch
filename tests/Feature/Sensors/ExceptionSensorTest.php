<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Types\Str;
use ReflectionClass;
use RuntimeException;
use Spatie\LaravelIgnition\IgnitionServiceProvider;
use stdClass;
use Tests\TestCase;
use Throwable;

use function array_map;
use function base64_encode;
use function base_path;
use function dirname;
use function fclose;
use function fopen;
use function gettype;
use function hash;
use function hex2bin;
use function implode;
use function ini_get;
use function ini_set;
use function json_decode;
use function json_encode;
use function report;
use function response;
use function str_contains;
use function tap;
use function trim;
use function version_compare;

class ExceptionSensorTest extends TestCase
{
    private array $iniSettingsToRestore = [];

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
        $this->setPhpVersion('8.4.1');
        $this->setLaravelVersion('11.33.0');
        $this->app->setBasePath($base = dirname($this->app->basePath()));
        $this->core->sensor->location->setBasePath($base);
        $this->core->sensor->location->setPublicPath($base.'/public');
        Config::set('app.debug', false);

        $this->iniSettingsToRestore['zend.exception_ignore_args'] = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->iniSettingsToRestore as $key => $value) {
            ini_set($key, $value);
        }
    }

    public function test_it_can_ingest_thrown_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        $trace = null;
        $line = null;
        Route::get('/users', function () use (&$trace, &$line): void {
            $line = __LINE__ + 1;
            $e = new MyException('Whoops!');

            $trace = $e->getTrace();

            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:*', [
            [
                'v' => 2,
                't' => 'exception',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', "Tests\Feature\Sensors\MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'GET /users',
                'execution_stage' => 'action',
                'user' => '',
                'class' => 'Tests\Feature\Sensors\MyException',
                'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
                'line' => $line,
                'message' => 'Whoops!',
                'code' => '0',
                'trace' => json_encode([
                    [
                        'file' => $this->core->sensor->location->normalizeFile(__FILE__).':'.$line,
                        'source' => '',
                    ],
                    ...array_map(fn ($frame) => [
                        'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                        'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {

                            'object' => $arg::class,
                            'string' => 'string',
                            'array' => 'array',
                        }, $frame['args'])).')',
                    ], $trace),
                ]),
                'handled' => false,
                'php_version' => '8.4.1',
                'laravel_version' => '11.33.0',
            ],
        ]);
    }

    public function test_it_captures_the_code(): void
    {
        $ingest = $this->fakeIngest();
        $line = null;
        Route::get('/users', function () use (&$line): void {
            $line = __LINE__ + 1;
            throw new MyException('Whoops!', 999);
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0._group', hash('xxh128', "Tests\Feature\Sensors\MyException,999,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"));
        $ingest->assertLatestWrite('exception:0.code', '999');
    }

    public function test_it_can_ingest_reported_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        $trace = null;
        $line = null;
        Route::get('/users', function () use (&$trace, &$line): void {
            $line = __LINE__ + 1;
            $e = new MyException('Whoops!');

            $trace = $e->getTrace();

            report($e);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:*', [
            [
                'v' => 2,
                't' => 'exception',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', "Tests\Feature\Sensors\MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'GET /users',
                'execution_stage' => 'action',
                'user' => '',
                'class' => 'Tests\Feature\Sensors\MyException',
                'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
                'line' => $line,
                'message' => 'Whoops!',
                'code' => '0',
                'trace' => json_encode([
                    [
                        'file' => $this->core->sensor->location->normalizeFile(__FILE__).':'.$line,
                        'source' => '',
                    ],
                    ...array_map(fn ($frame) => [
                        'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                        'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {

                            'object' => $arg::class,
                            'string' => 'string',
                            'array' => 'array',
                        }, $frame['args'])).')',
                    ], $trace),
                ]),
                'handled' => true,
                'php_version' => '8.4.1',
                'laravel_version' => '11.33.0',
            ],
        ]);
    }

    public function test_it_captures_aggregate_exception_data_on_the_request(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            report(new RuntimeException('Whoops!'));
            report(new RuntimeException('Whoops!'));
            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.exceptions', 3);
    }

    public function test_it_handles_view_exceptions(): void
    {
        $this->assertFalse(App::providerIsLoaded(IgnitionServiceProvider::class));

        $ingest = $this->fakeIngest();
        Route::view('exception', 'exception');

        $response = $this->get('exception');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.line', 0);
        $ingest->assertLatestWrite('exception:0.file', 'workbench/resources/views/exception.blade.php');
        $ingest->assertLatestWrite('exception:0.class', 'Exception');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('exception:0.code', '999');
        $ingest->assertLatestWrite('exception:0._group', hash('xxh128', 'Exception,999,workbench/resources/views/exception.blade.php,'));
    }

    public function test_it_handles_spatie_view_exceptions(): void
    {
        App::register(IgnitionServiceProvider::class);
        $this->assertTrue(App::providerIsLoaded(IgnitionServiceProvider::class));

        $ingest = $this->fakeIngest();
        Route::view('exception', 'exception');

        $response = $this->get('exception');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.line', 6);
        $ingest->assertLatestWrite('exception:0.file', 'workbench/resources/views/exception.blade.php');
        $ingest->assertLatestWrite('exception:0.class', 'Exception');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('exception:0.code', '999');
        $ingest->assertLatestWrite('exception:0._group', hash('xxh128', 'Exception,999,workbench/resources/views/exception.blade.php,6'));
    }

    public function test_it_skips_internal_frames_on_php_errors(): void
    {
        $ingest = $this->fakeIngest();
        $line = __LINE__ + 3;
        Route::get('/users', function (): void {
            $foo = [];
            $foo[0];
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.message', 'Undefined array key 0');
        $ingest->assertLatestWrite('exception:0.class', 'ErrorException');
        $ingest->assertLatestWrite('exception:0.file', 'tests/Feature/Sensors/ExceptionSensorTest.php');
        $ingest->assertLatestWrite('exception:0.line', $line);
        $ingest->assertLatestWrite('exception:0.trace', function ($trace) use ($line) {
            $trace = json_decode($trace, associative: true);

            $this->assertSame('tests/Feature/Sensors/ExceptionSensorTest.php:'.$line, $trace[0]['file']);

            foreach ($trace as $frame) {
                $this->assertStringNotContainsString('HandleExceptions', $frame['file']);
                $this->assertStringNotContainsString('HandleExceptions', $frame['source']);
            }

            return true;
        });
    }

    public function test_it_handles_unknown_lines_for_internal_locations(): void
    {
        $ingest = $this->fakeIngest();
        $e = new Exception('Whoops!');
        $reflectedException = new ReflectionClass($e);
        $reflectedException->getProperty('file')->setValue($e, base_path('vendor/foo/bar/Baz.php'));
        $reflectedException->getProperty('trace')->setValue($e, [
            [
                'file' => base_path('app/Models/User.php'),
            ],
        ]);
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.file', 'app/Models/User.php');
        $ingest->assertLatestWrite('exception:0.line', 0);
    }

    public function test_it_captures_handled_and_unhandled_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        $e = new Exception('Whoops!');
        Route::get('/users', function () use ($e): void {
            report($e);

            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.handled', true);
        $ingest->assertLatestWrite('exception:1.handled', false);
    }

    public function test_it_handles_the_file_in_the_trace(): void
    {
        $ingest = $this->fakeIngest();
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
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', json_encode([
            [
                'file' => $this->core->sensor->location->normalizeFile($e->getFile()).':'.$e->getLine(),
                'source' => '',
            ],
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
    }

    public function test_it_handles_the_line_in_the_trace(): void
    {
        $ingest = $this->fakeIngest();
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
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', json_encode([
            [
                'file' => $this->core->sensor->location->normalizeFile($e->getFile()).':'.$e->getLine(),
                'source' => '',
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
                'file' => '[internal function]:5',
                'source' => '()',
            ],
        ]));
    }

    public function test_it_handles_the_class_in_the_trace(): void
    {
        $ingest = $this->fakeIngest();
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
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', json_encode([
            [
                'file' => $this->core->sensor->location->normalizeFile($e->getFile()).':'.$e->getLine(),
                'source' => '',
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
                'source' => 'TheClass()',
            ],
        ]));
    }

    public function test_it_handles_the_function_in_the_trace(): void
    {
        $ingest = $this->fakeIngest();
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
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', json_encode([
            [
                'file' => $this->core->sensor->location->normalizeFile($e->getFile()).':'.$e->getLine(),
                'source' => '',
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
                'source' => 'the_function()',
            ],
        ]));
    }

    public function test_it_handles_the_args_in_the_trace(): void
    {
        $ingest = $this->fakeIngest();
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
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', json_encode([
            [
                'file' => $this->core->sensor->location->normalizeFile($e->getFile()).':'.$e->getLine(),
                'source' => '',
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
                'source' => '()',
            ],
            [
                'file' => '[internal function]',
                'source' => '(null, bool, int, float, string, array, stdClass, Tests\Feature\Sensors\MyEnum, Closure, resource, resource (closed))',
            ],
        ]));

        fclose($resourceToClose);
    }

    public function test_it_handles_named_arguments_for_variadic_functions(): void
    {
        $args = [];
        try {
            (fn (...$args) => throw new Exception('Whoops!'))(foo: 1, bar: 2);
        } catch (Throwable $e) {
            $args = $e->getTrace()[0]['args'];
        }
        $ingest = $this->fakeIngest();
        $e = new Exception('Whoops!');
        $reflectedException = new ReflectionClass($e);
        $reflectedException->getProperty('trace')->setValue($e, [
            [
                'args' => $args,
            ],
        ]);
        Route::get('/users', function () use ($e): void {
            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', json_encode([
            [
                'file' => $this->core->sensor->location->normalizeFile($e->getFile()).':'.$e->getLine(),
                'source' => '',
            ],
            [
                'file' => '[internal function]',
                'source' => '(foo: int, bar: int)',
            ],
        ]));
    }

    public function test_it_handles_ini_setting_disabling_args_in_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        $function = __FUNCTION__;
        $line = __LINE__ + 1;
        Route::get('/users', function (Request $request): void {
            throw new RuntimeException;
        });

        ini_set('zend.exception_ignore_args', '1');
        $response = $this->get('/users');
        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        if (version_compare(PHP_VERSION, '8.4', '<')) {
            $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => ! str_contains($trace, '{closure}(Illuminate\\\\Http\\\\Request)'));
        } else {
            $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => ! str_contains($trace, trim(json_encode('{closure:'.static::class.'::'.$function.'():'.$line.'}(Illuminate\\Http\\Request)'), '"')));
        }

        ini_set('zend.exception_ignore_args', '0');
        $response = $this->get('/users');
        $response->assertServerError();
        $ingest->assertWrittenTimes(2);
        if (version_compare(PHP_VERSION, '8.4', '<')) {
            $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => str_contains($trace, '{closure}(Illuminate\\\\Http\\\\Request)'));
        } else {
            $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => str_contains($trace, trim(json_encode('{closure:'.static::class.'::'.$function.'():'.$line.'}(Illuminate\\Http\\Request)'), '"')));
        }
    }

    public function test_it_strips_base_path_from_trace_files(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            throw new RuntimeException;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', fn ($trace) => str_contains($trace, '"file":"vendor\/laravel\/framework\/src\/Illuminate\/Routing\/Route.php:'));
    }

    public function test_it_can_manually_report_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        $trace = null;
        $line = null;
        Route::get('/users', function () use (&$trace, &$line): void {
            $line = __LINE__ + 1;
            $e = new MyException('Whoops!');

            $trace = $e->getTrace();

            Nightwatch::report($e);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:*', [
            [
                'v' => 2,
                't' => 'exception',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', "Tests\Feature\Sensors\MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'GET /users',
                'execution_stage' => 'action',
                'user' => '',
                'class' => 'Tests\Feature\Sensors\MyException',
                'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
                'line' => $line,
                'message' => 'Whoops!',
                'code' => '0',
                'trace' => json_encode([
                    [
                        'file' => $this->core->sensor->location->normalizeFile(__FILE__).':'.$line,
                        'source' => '',
                    ],
                    ...array_map(fn ($frame) => [
                        'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                        'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {

                            'object' => $arg::class,
                            'string' => 'string',
                            'array' => 'array',
                        }, $frame['args'])).')',
                    ], $trace),
                ]),
                'handled' => false,
                'php_version' => '8.4.1',
                'laravel_version' => '11.33.0',
            ],
        ]);
    }

    public function test_it_handles_pdo_exceptions_where_the_code_is_a_string(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            DB::table('__foo__')->get();
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.code', 'HY000');
    }

    public function test_it_can_capture_exception_messages_containing_binary(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            DB::table('unknown-table')->where('foo', hex2bin('abc123'))->get();
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.message', function ($message) {
            $this->assertSame(
                base64_encode($message),
                base64_encode('SQLSTATE[HY000]: General error: 1 no such table: unknown-table (Connection: sqlite, SQL: select * from "unknown-table" where "foo" = ��#)')
            );

            return true;
        });
    }

    public function test_it_reports_internally_reported_exceptions_as_handled()
    {
        $ingest = $this->fakeIngest();
        $this->core->sensor->cacheEventSensor = function () {
            throw new RuntimeException('Whoops!');
        };
        Route::get('/test', function () {
            Cache::get('key');
        });

        $response = $this->get('/test');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.handled', true);
    }
}

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
