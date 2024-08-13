<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelIgnition\IgnitionServiceProvider;

use function Pest\Laravel\get;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    Config::set('app.debug', false);
    App::setBasePath(realpath(__DIR__.'/../../../'));
});

it('can ingest thrown exceptions', function () {
    $ingest = fakeIngest();
    $trace = null;
    $line = null;
    Route::get('/users', function () use (&$trace, &$line) {
        $line = __LINE__ + 1;
        $e = new MyException('Whoops!');

        $trace = $e->getTraceAsString();

        throw $e;
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exceptions', [
        [
            'v' => 1,
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('md5', "MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'class' => 'MyException',
            'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
            'line' => $line,
            'message' => 'Whoops!',
            'code' => 0,
            'trace' => $trace,
            'handled' => false,
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
    $ingest->assertLatestWrite('exceptions.0._group', hash('md5', "MyException,999,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"));
    $ingest->assertLatestWrite('exceptions.0.code', 999);
});

it('can ingest reported exceptions', function () {
    $ingest = fakeIngest();
    $trace = null;
    $line = null;
    Route::get('/users', function () use (&$trace, &$line) {
        $line = __LINE__ + 1;
        $e = new MyException('Whoops!');

        $trace = $e->getTraceAsString();

        report($e);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exceptions', [
        [
            'v' => 1,
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('md5', "MyException,0,tests/Feature/Sensors/ExceptionSensorTest.php,{$line}"),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'execution_context' => 'request',
            'execution_id' => '00000000-0000-0000-0000-000000000001',
            'execution_stage' => 'action',
            'user' => '',
            'class' => 'MyException',
            'file' => 'tests/Feature/Sensors/ExceptionSensorTest.php',
            'line' => $line,
            'message' => 'Whoops!',
            'code' => 0,
            'trace' => $trace,
            'handled' => true,
        ],
    ]);
});

it('captures aggregate query data on the request', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        report(new RuntimeException('Whoops!'));
        report(new RuntimeException('Whoops!'));
        throw new RuntimeException('Whoops!');
    });

    $response = get('/users');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.exceptions', 3);
});

it('handles view exceptions', function () {
    expect(App::providerIsLoaded(IgnitionServiceProvider::class))->toBe(false);

    App::setBasePath(realpath(__DIR__.'/../../../../nightwatch/workbench'));
    $ingest = fakeIngest();
    Route::view('exception', 'exception');

    $response = get('exception');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exceptions.0.line', 0);
    $ingest->assertLatestWrite('exceptions.0.file', 'resources/views/exception.blade.php');
    $ingest->assertLatestWrite('exceptions.0.class', 'Exception');
    $ingest->assertLatestWrite('exceptions.0.message', 'Whoops!');
    $ingest->assertLatestWrite('exceptions.0.code', 999);
    $ingest->assertLatestWrite('exceptions.0._group', hash('md5', 'Exception,999,resources/views/exception.blade.php,'));
});

it('handles spatie view exceptions', function () {
    App::register(IgnitionServiceProvider::class);
    expect(App::providerIsLoaded(IgnitionServiceProvider::class))->toBe(true);

    App::setBasePath(realpath(__DIR__.'/../../../../nightwatch/workbench'));
    $ingest = fakeIngest();
    Route::view('exception', 'exception');

    $response = get('exception');

    $response->assertServerError();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('exceptions.0.line', 6);
    $ingest->assertLatestWrite('exceptions.0.file', 'resources/views/exception.blade.php');
    $ingest->assertLatestWrite('exceptions.0.class', 'Exception');
    $ingest->assertLatestWrite('exceptions.0.message', 'Whoops!');
    $ingest->assertLatestWrite('exceptions.0.code', 999);
    $ingest->assertLatestWrite('exceptions.0._group', hash('md5', 'Exception,999,resources/views/exception.blade.php,6'));
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
    $ingest->assertLatestWrite('exceptions.0.file', 'app/Models/User.php');
    $ingest->assertLatestWrite('exceptions.0.line', 0);
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
    $ingest->assertLatestWrite('exceptions.0.handled', true);
    $ingest->assertLatestWrite('exceptions.1.handled', false);
});

final class MyException extends RuntimeException
{
    public function render()
    {
        return response('', 500);
    }
}
