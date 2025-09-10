<?php

namespace Tests\Feature\Sensors;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Attributes\WithEnv;
use ReflectionClass;
use RuntimeException;
use Spatie\LaravelIgnition\IgnitionServiceProvider;
use stdClass;
use Tests\TestCase;
use Throwable;

use function array_map;
use function base64_encode;
use function base_path;
use function collect;
use function dirname;
use function fclose;
use function fopen;
use function gettype;
use function hash;
use function hex2bin;
use function implode;
use function ini_get;
use function ini_set;
use function is_array;
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
        Env::getRepository()->set('NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE', '0');

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
                'v' => 3,
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
                        'code' => null,
                    ],
                    ...array_map(fn ($frame) => [
                        'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                        'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {

                            'object' => $arg::class,
                            'string' => 'string',
                            'array' => 'array',
                        }, $frame['args'])).')',
                        'code' => null,
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
                'v' => 3,
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
                        'code' => null,
                    ],
                    ...array_map(fn ($frame) => [
                        'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                        'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {

                            'object' => $arg::class,
                            'string' => 'string',
                            'array' => 'array',
                        }, $frame['args'])).')',
                        'code' => null,
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

    #[WithEnv('NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE', '0')]
    public function test_it_can_disable_source_code_capture(): void
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
        $records = $ingest->decodedWrites()->last();
        $record = collect($records)->where('t', 'exception')->first();

        $this->assertSame('Tests\Feature\Sensors\MyException', $record['class']);
        $this->assertSame('tests/Feature/Sensors/ExceptionSensorTest.php', $record['file']);
        $this->assertSame($line, $record['line']);
        $this->assertSame('Whoops!', $record['message']);
        $this->assertTrue($record['handled']);

        $this->assertArrayNotHasKey('source_lines', $record);

        $trace = json_decode($record['trace'], true);
        $this->assertIsArray($trace);

        foreach ($trace as $frame) {
            $this->assertArrayNotHasKey('source_lines', $frame, 'Trace frames should not include source lines when feature is disabled');
        }
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
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[unknown file]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => 'the/file.php',
                'source' => '()',
                'code' => null,
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
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]:5',
                'source' => '()',
                'code' => null,
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
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => 'TheClass()',
                'code' => null,
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
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => 'the_function()',
                'code' => null,
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
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '()',
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '(null, bool, int, float, string, array, stdClass, Tests\Feature\Sensors\MyEnum, Closure, resource, resource (closed))',
                'code' => null,
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
                'code' => null,
            ],
            [
                'file' => '[internal function]',
                'source' => '(foo: int, bar: int)',
                'code' => null,
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
                'v' => 3,
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
                        'code' => null,
                    ],
                    ...array_map(fn ($frame) => [
                        'file' => Str::after($frame['file'] ?? '[internal function]', base_path().DIRECTORY_SEPARATOR).(isset($frame['line']) ? ':'.$frame['line'] : ''),
                        'source' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'('.implode(', ', array_map(fn ($arg) => match (gettype($arg)) {

                            'object' => $arg::class,
                            'string' => 'string',
                            'array' => 'array',
                        }, $frame['args'])).')',
                        'code' => null,
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

    #[WithEnv('NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE', '1')]
    public function test_it_captures_source_code_lines(): void
    {
        $ingest = $this->fakeIngest();

        $response = $this->get('/test-exception');
        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', function ($value) {
            $frames = collect(json_decode($value, true));

            $this->assertEquals([
                23 => '    /**',
                24 => '     * Get the message envelope.',
                25 => '     */',
                26 => '    public function envelope(): Envelope',
                27 => '    {',
                28 => '        return new Envelope(',
                29 => '            subject: $this->subject,',
                30 => '        );',
                31 => '    }',
                32 => '',
                33 => '    /**',
            ], $frames->firstWhere('file', 'workbench/app/Mail/MyMail.php:28')['code']);

            $this->assertEquals([
                13 => 'final class ExceptionTestController',
                14 => '{',
                15 => '    public function __invoke()',
                16 => '    {',
                17 => '        try {',
                18 => '            Mail::to(\'test@test.com\')->send(new MyMail([\'effect\' => \'This explodes\']));',
                19 => '        } catch (Exception $e) {',
                20 => '            report($e);',
                21 => '',
                22 => '            abort(500, \'Exploding as expected\');',
                23 => '        }',
            ], $frames->firstWhere('file', 'workbench/app/Http/ExceptionTestController.php:18')['code']);

            $this->assertEquals([
                29 => '            $this->nightwatch->stage(ExecutionStage::Action);',
                30 => '        } catch (Throwable $e) {',
                31 => '            $this->nightwatch->report($e, handled: true);',
                32 => '        }',
                33 => '',
                34 => '        $response = $next($request);',
                35 => '',
                36 => '        // If an exception occurs in the action phase, the usual',
                37 => '        // ResponsePrepared event is not fired. This fallback',
                38 => '        // ensures that we go to the AfterMiddleware stage.',
                39 => '        try {',
            ], $frames->firstWhere('file', 'src/Hooks/RouteMiddleware.php:34')['code']);

            $this->assertEquals([
                48 => '            $this->nightwatch->captureRequestPreview($request);',
                49 => '        } catch (Throwable $e) {',
                50 => '            $this->nightwatch->report($e, handled: true);',
                51 => '        }',
                52 => '',
                53 => '        return $next($request);',
                54 => '    }',
                55 => '',
                56 => '    public function terminate(Request $request, Response $response): void',
                57 => '    {',
                58 => '        if ($this->hasTerminated || Compatibility::$terminatingEventExists) {',
            ], $frames->firstWhere('file', 'src/Hooks/GlobalMiddleware.php:53')['code']);

            return true;
        });
    }

    #[WithEnv('NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE', '1')]
    public function test_it_captures_code_from_a_maximum_of_ten_frames(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            $e = new Exception('Whoops!');
            $reflectedException = new ReflectionClass($e);
            $reflectedException->getProperty('trace')->setValue($e, [
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
                [
                    'file' => __FILE__,
                    'line' => 1,
                ],
            ]);

            throw $e;
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:0.trace', function ($trace) {
            $trace = collect(json_decode($trace, associative: true));

            $this->assertCount(10, $trace->where(fn ($frame) => is_array($frame['code'])));

            return true;
        });
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
