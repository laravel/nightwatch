<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\SensorManager;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\head;
use function Pest\Laravel\travel;

beforeEach(function () {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemoryInKilobytes(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 00:00:00')->startOfSecond());
});

it('can ingest requests', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests', [
        [
            'v' => 1,
            'timestamp' => 946684800,
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => '875befe7cefc0715a17dc737f9514dda981f79a3c9f174badcae5bd1cc2425fe',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'user' => '',
            'method' => 'GET',
            'scheme' => 'http',
            'url_user' => '',
            'host' => 'localhost',
            'port' => '80',
            'path' => '/users',
            'query' => '',
            'route_name' => '',
            'route_methods' => ['GET', 'HEAD'],
            'route_domain' => '',
            'route_path' => '/users',
            'route_action' => 'Closure',
            'ip' => '127.0.0.1',
            'duration' => 0,
            'status_code' => '200',
            'request_size' => 0,
            'response_size' => 2,
            'queries' => 0,
            'queries_duration' => 0,
            'lazy_loads' => 0,
            'lazy_loads_duration' => 0,
            'jobs_queued' => 0,
            'mail_queued' => 0,
            'mail_sent' => 0,
            'mail_duration' => 0,
            'notifications_queued' => 0,
            'notifications_sent' => 0,
            'notifications_duration' => 0,
            'outgoing_requests' => 0,
            'outgoing_requests_duration' => 0,
            'files_read' => 0,
            'files_read_duration' => 0,
            'files_written' => 0,
            'files_written_duration' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage_kilobytes' => 1234,
            'global_before_middleware' => 0,
            'route_before_middleware' => 0,
            'main' => 0,
            'main_render' => 0,
            'route_after_middleware' => 0,
            'route_after_middleware_render' => 0,
            'global_after_middleware' => 0,
            'response_transmission' => 0,
            'terminate' => 0,
        ],
    ]);
});

it('captures the response size', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => '[{"name":"Tim"}]');

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.response_size', 16);
});

it('captures the response size of a streamed file', function () {
    $ingest = fakeIngest();
    Route::get('users', fn () => response()->file(fixturePath('empty-array.json')));

    $response = get('/users');

    $ingest->assertLatestWrite('requests.0.response_size', 17);
});

it('gracefully handles response size for a streamed file that is deleted after sending the response', function () {
    // Testing this normally is hard. Laravel does not call `send` for
    // responses so we need to handle is pretty manually in this test.
    $ingest = fakeIngest();
    $sensor = app(SensorManager::class);
    $request = Request::create('http://localhost/users');

    $file = tmpfile();
    fwrite($file, '[{"name":"Tim"}]');
    fseek($file, 0);

    ob_start();
    $response = response()->file(stream_get_meta_data($file)['uri'])->deleteFileAfterSend()->sendContent();
    ob_end_clean();

    $sensor->request($request, $response);
    $ingest->write($sensor->flush());

    $ingest->assertLatestWrite('requests.0.response_size', null);
});

it('gracefully handles response size for streamed responses', function () {
    $ingest = fakeIngest();
    Route::get('users', fn () => response()->stream(function () {
        echo '[]';
    }));

    get('/users');

    $ingest->assertLatestWrite('requests.0.response_size', null);
});

it('captures the content-length when present on a streamed response', function () {
    $ingest = fakeIngest();
    Route::get('users', fn () => response()->stream(function () {
        echo '[]';
    }, headers: ['Content-length' => 2]));

    get('/users');

    $ingest->assertLatestWrite('requests.0.response_size', 2);
});

it('uses the content-length header as the response size when present on a streamed file response where the file is deleted after sending', function () {
    $ingest = fakeIngest();
    $sensor = app(SensorManager::class);
    $request = Request::create('http://localhost/users');

    $file = tmpfile();
    fwrite($file, '[{"name":"Tim"}]');
    fseek($file, 0);

    ob_start();
    $response = response()->file(stream_get_meta_data($file)['uri'], headers: ['Content-length' => 17])->deleteFileAfterSend()->sendContent();
    ob_end_clean();

    $sensor->request($request, $response);
    $ingest->write($sensor->flush());

    $ingest->assertLatestWrite('requests.0.response_size', 17);
});

it('captures the request size', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = call('GET', '/users', content: 'abc');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.request_size', 3);
});

it('captures the user when authenticated', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = actingAs(new GenericUser(['id' => 'abc-123']))
        ->get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.user', 'abc-123');
});

it('uses the default port for the scheme when not port is available to the request', function () {
    $ingest = fakeIngest();
    $sensor = app(SensorManager::class);

    $request = (new class extends Request
    {
        public function getPort(): int|string|null
        {
            return null;
        }
    })::create('https://laravel.com/users');
    $sensor->request($request, response(''));
    $ingest->write($sensor->flush());

    expect($request->getPort())->toBeNull();
    expect($request->getScheme())->toBe('https');
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.port', '443');

    $request = (new class extends Request
    {
        public function getPort(): int|string|null
        {
            return null;
        }
    })::create('http://laravel.com/users');
    $sensor->request($request, response(''));
    $ingest->write($sensor->flush());

    expect($request->getPort())->toBeNull();
    expect($request->getScheme())->toBe('http');
    $ingest->assertWrittenTimes(2);
    $ingest->assertLatestWrite('requests.0.port', '80');
});

it('captures query parameters', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('/users?key_1=value&key_2[sub_field]=value&key_3[]=value&key_4[9]=value&key_5[][][foo][9]=bar&flag_value');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.query', 'key_1=value&key_2[sub_field]=value&key_3[]=value&key_4[9]=value&key_5[][][foo][9]=bar&flag_value');
});

it('captures the route name', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => [])->name('users.index');

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.route_name', 'users.index');
});

it('captures the route methods', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.route_methods', ['GET', 'HEAD']);
});

it('captures route actions for closures', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.route_action', 'Closure');
});

it('captures route actions for controller classes', function () {
    $ingest = fakeIngest();
    Route::get('/users', [UserController::class, 'index']);

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.route_action', 'UserController@index');
});

it('captures real path and route path', function () {
    $ingest = fakeIngest();
    Route::get('/users/{user}', fn () => ['name' => 'Tim']);

    $response = get('/users/123');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.path', '/users/123');
    $ingest->assertLatestWrite('requests.0.route_path', '/users/{user}');
});

it('captures subdomain and route domain', function () {
    $ingest = fakeIngest();
    Route::domain('{product}.laravel.com')->get('/users/{user}', fn () => ['name' => 'Tim']);

    $response = get('http://forge.laravel.com/users/123');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.host', 'forge.laravel.com');
    $ingest->assertLatestWrite('requests.0.route_domain', '{product}.laravel.com');
});

it('foo', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('http://tim:secret@localhost/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.url_user', 'tim');
    expect($ingest->latestWriteAsString())->not->toContain('secret');
});

it('records the requests user whilst ommiting the password', function () {
    $ingest = fakeIngest();
    Route::domain('{product}.laravel.com')->get('/users/{user}', fn () => ['name' => 'Tim']);

    $response = get('http://forge.laravel.com/users/123');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.host', 'forge.laravel.com');
    $ingest->assertLatestWrite('requests.0.route_domain', '{product}.laravel.com');
});

it('captures the duration in microseconds', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMicroseconds(5));

        return [];
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.duration', 5);
});

it('consistently sorts the route methods', function () {
    $ingest = fakeIngest();
    Route::match(['GET', 'POST', 'PATCH'], '/users', fn () => []);
    Route::match(['PATCH', 'POST', 'GET'], '/users/{user}', fn () => []);

    $response = get('/users');
    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.route_methods', ['GET', 'HEAD', 'PATCH', 'POST']);

    $response = get('/users/123');
    $response->assertOk();
    $ingest->assertWrittenTimes(2);
    $ingest->assertLatestWrite('requests.0.route_methods', ['GET', 'HEAD', 'PATCH', 'POST']);
});

it('handles HEAD requests', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = head('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.response_size', 0);
});

it('handles 204 no content requests', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => response('foo', 204));

    $response = head('/users');

    $response->assertNoContent();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.response_size', 0);
});

it('captures aggregate query data', function () {
    prependListener(QueryExecuted::class, function (QueryExecuted $event) {
        // Ensure we don't capture migration queries.
        if (! RefreshDatabaseState::$migrated) {
            return false;
        }

        $event->time = 5.2;

        travel(now()->addMilliseconds(5.2));
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
    $ingest->assertLatestWrite('requests.0.queries_duration', 10400);
});

final class UserController
{
    public function index()
    {
        return [];
    }
}
