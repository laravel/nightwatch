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
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
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
use function config;
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
        Config::set('nightwatch.exceptions.capture_source_lines', false);

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
                'v' => 1,
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
                'v' => 1,
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

    public function test_it_can_disable_source_code_capture(): void
    {
        config(['nightwatch.exceptions.capture_source_lines' => false]);

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
                'v' => 1,
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

    public function test_it_captures_source_code_lines(): void
    {
        Config::set('nightwatch.exceptions.capture_source_lines', true);

        $ingest = $this->fakeIngest();

        $response = $this->get('/test-exception');
        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('exception:*', [
            [
                'v' => 1,
                't' => 'exception',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'TypeError,0,workbench/app/Mail/MyMail.php,28'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'execution_source' => 'request',
                'execution_id' => '00000000-0000-0000-0000-000000000001',
                'execution_preview' => 'GET /test-exception',
                'execution_stage' => 'action',
                'user' => '',
                'class' => 'TypeError',
                'file' => 'workbench/app/Mail/MyMail.php',
                'line' => 28,
                'message' => 'Illuminate\Mail\Mailables\Envelope::__construct(): Argument #6 ($subject) must be of type ?string, array given, called in /Users/philliphartin/Development/nightwatch/workbench/app/Mail/MyMail.php on line 28',
                'code' => '0',
                'trace' => json_encode([
                    [
                        'file' => 'workbench/app/Mail/MyMail.php:28',
                        'source' => 'Illuminate\\Mail\\Mailables\\Envelope->__construct(null, array, array, array, array, array)',
                        'source_lines' => [
                            'file' => 'workbench/app/Mail/MyMail.php',
                            'line' => 28,
                            'lines' => [
                                '    /**',
                                '     * Get the message envelope.',
                                '     */',
                                '    public function envelope(): Envelope',
                                '    {',
                                '        return new Envelope(',
                                '            subject: $this->subject,',
                                '        );',
                                '    }',
                                '',
                                '    /**',
                            ],
                            'start_line' => 23,
                            'end_line' => 33,
                        ],
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailable.php:1728',
                        'source' => 'App\\Mail\\MyMail->envelope()',
                        'source_lines' => [
                            'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailable.php',
                            'line' => 1728,
                            'lines' => [
                                '    {',
                                '        if (! method_exists($this, \'envelope\')) {',
                                '            return;',
                                '        }',
                                '',
                                '        $envelope = $this->envelope();',
                                '',
                                '        if (isset($envelope->from)) {',
                                '            $this->from($envelope->from->address, $envelope->from->name);',
                                '        }',
                                '',
                            ],
                            'start_line' => 1723,
                            'end_line' => 1733,
                        ],
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailable.php:1684',
                        'source' => 'Illuminate\\Mail\\Mailable->ensureEnvelopeIsHydrated()',
                        'source_lines' => [
                            'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailable.php',
                            'line' => 1684,
                            'lines' => [
                                '        if (method_exists($this, \'build\')) {',
                                '            Container::getInstance()->call([$this, \'build\']);',
                                '        }',
                                '',
                                '        $this->ensureHeadersAreHydrated();',
                                '        $this->ensureEnvelopeIsHydrated();',
                                '        $this->ensureContentIsHydrated();',
                                '        $this->ensureAttachmentsAreHydrated();',
                                '    }',
                                '',
                                '    /**',
                            ],
                            'start_line' => 1679,
                            'end_line' => 1689,
                        ],
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailable.php:201',
                        'source' => 'Illuminate\\Mail\\Mailable->prepareMailableForDelivery()',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Support/Traits/Localizable.php:19',
                        'source' => 'Illuminate\\Mail\\Mailable->{closure:Illuminate\\Mail\\Mailable::send():200}()',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailable.php:200',
                        'source' => 'Illuminate\\Mail\\Mailable->withLocale(null, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailer.php:353',
                        'source' => 'Illuminate\\Mail\\Mailable->send(Illuminate\\Mail\\Mailer)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/Mailer.php:300',
                        'source' => 'Illuminate\\Mail\\Mailer->sendMailable(App\\Mail\\MyMail)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Mail/PendingMail.php:123',
                        'source' => 'Illuminate\\Mail\\Mailer->send(App\\Mail\\MyMail)',
                    ],
                    [
                        'file' => 'workbench/app/Http/ExceptionTestController.php:12',
                        'source' => 'Illuminate\\Mail\\PendingMail->send(App\\Mail\\MyMail)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php:46',
                        'source' => 'App\\Http\\ExceptionTestController->__invoke()',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Route.php:265',
                        'source' => 'Illuminate\\Routing\\ControllerDispatcher->dispatch(Illuminate\\Routing\\Route, App\\Http\\ExceptionTestController, string)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Route.php:211',
                        'source' => 'Illuminate\\Routing\\Route->runController()',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php:808',
                        'source' => 'Illuminate\\Routing\\Route->run()',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:169',
                        'source' => 'Illuminate\\Routing\\Router->{closure:Illuminate\\Routing\\Router::runRouteWithinStack():807}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'src/Hooks/RouteMiddleware.php:34',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():167}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Laravel\\Nightwatch\\Hooks\\RouteMiddleware->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php:50',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Routing\\Middleware\\SubstituteBindings->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/VerifyCsrfToken.php:87',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php:48',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\View\\Middleware\\ShareErrorsFromSession->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:120',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php:63',
                        'source' => 'Illuminate\\Session\\Middleware\\StartSession->handleStatefulRequest(Illuminate\\Http\\Request, Illuminate\\Session\\Store, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Session\\Middleware\\StartSession->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php:36',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php:74',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:126',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php:807',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->then(Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php:786',
                        'source' => 'Illuminate\\Routing\\Router->runRouteWithinStack(Illuminate\\Routing\\Route, Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php:750',
                        'source' => 'Illuminate\\Routing\\Router->runRoute(Illuminate\\Http\\Request, Illuminate\\Routing\\Route)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Routing/Router.php:739',
                        'source' => 'Illuminate\\Routing\\Router->dispatchToRoute(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:200',
                        'source' => 'Illuminate\\Routing\\Router->dispatch(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:169',
                        'source' => 'Illuminate\\Foundation\\Http\\Kernel->{closure:Illuminate\\Foundation\\Http\\Kernel::dispatchToRouter():197}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/livewire/livewire/src/Features/SupportDisablingBackButtonCache/DisableBackButtonCacheMiddleware.php:19',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:Illuminate\\Pipeline\\Pipeline::prepareDestination():167}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Livewire\\Features\\SupportDisablingBackButtonCache\\DisableBackButtonCacheMiddleware->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/vapor-core/src/Http/Middleware/ServeStaticAssets.php:21',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Laravel\\Vapor\\Http\\Middleware\\ServeStaticAssets->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php:21',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php:31',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php:21',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php:51',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\TransformsRequest->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\TrimStrings->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php:27',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Http\\Middleware\\ValidatePostSize->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php:109',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php:48',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Http\\Middleware\\HandleCors->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:58',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Http\\Middleware\\TrustProxies->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php:22',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php:26',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Illuminate\\Http\\Middleware\\ValidatePathEncoding->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'src/Hooks/GlobalMiddleware.php:53',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:208',
                        'source' => 'Laravel\\Nightwatch\\Hooks\\GlobalMiddleware->handle(Illuminate\\Http\\Request, Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php:126',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->{closure:{closure:Illuminate\\Pipeline\\Pipeline::carry():183}:184}(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:175',
                        'source' => 'Illuminate\\Pipeline\\Pipeline->then(Closure)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:144',
                        'source' => 'Illuminate\\Foundation\\Http\\Kernel->sendRequestThroughRouter(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php:607',
                        'source' => 'Illuminate\\Foundation\\Http\\Kernel->handle(Illuminate\\Http\\Request)',
                    ],
                    [
                        'file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php:368',
                        'source' => 'Orchestra\\Testbench\\TestCase->call(string, string, array, array, array, array)',
                    ],
                    [
                        'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php:745',
                        'source' => 'Orchestra\\Testbench\\TestCase->get(string)',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestCase.php:1656',
                        'source' => 'Tests\\Feature\\Sensors\\ExceptionSensorTest->test_it_captures_source_code_lines()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestCase.php:514',
                        'source' => 'PHPUnit\\Framework\\TestCase->runTest()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php:87',
                        'source' => 'PHPUnit\\Framework\\TestCase->runBare()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestCase.php:361',
                        'source' => 'PHPUnit\\Framework\\TestRunner->run(Tests\\Feature\\Sensors\\ExceptionSensorTest)',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestSuite.php:369',
                        'source' => 'PHPUnit\\Framework\\TestCase->run()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestSuite.php:369',
                        'source' => 'PHPUnit\\Framework\\TestSuite->run()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/Framework/TestSuite.php:369',
                        'source' => 'PHPUnit\\Framework\\TestSuite->run()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/TextUI/TestRunner.php:64',
                        'source' => 'PHPUnit\\Framework\\TestSuite->run()',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/src/TextUI/Application.php:210',
                        'source' => 'PHPUnit\\TextUI\\TestRunner->run(PHPUnit\\TextUI\\Configuration\\Configuration, PHPUnit\\Runner\\ResultCache\\DefaultResultCache, PHPUnit\\Framework\\TestSuite)',
                    ],
                    [
                        'file' => 'vendor/phpunit/phpunit/phpunit:104',
                        'source' => 'PHPUnit\\TextUI\\Application->run(array)',
                    ],
                    [
                        'file' => 'vendor/bin/phpunit:122',
                        'source' => 'include(string)',
                    ],
                ]),
                'handled' => false,
                'php_version' => '8.4.1',
                'laravel_version' => '11.33.0',
            ],
        ]);
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
