<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\File\Stream;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

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
            'group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'user' => '',
            'method' => 'GET',
            'scheme' => 'http',
            'url_user' => '',
            'host' => 'localhost',
            'port' => '80',
            'path' => '/users',
            'query' => [],
            'route_name' => '',
            'route_methods' => ['GET', 'HEAD'],
            'route_domain' => '',
            'route_path' => '/users',
            'route_action' => 'Closure',
            'ip' => '127.0.0.1',
            'duration' => 0,
            'status_code' => '200',
            'request_size_kilobytes' => 0,
            'response_size_kilobytes' => 0,
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

it('captures request duration', function () {
    $ingest = fakeIngest();
    Route::get('/users', function () {
        travelTo(now()->addMilliseconds(1234));

        return [];
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.duration', 1234);
});

it('captures the response size', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => str_repeat('a', 2000));

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.response_size_kilobytes', 2);
});

it('captures the request size', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = call('GET', '/users', content: str_repeat('b', 3000));

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.request_size_kilobytes', 3);
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

    $request = (new class extends Request {
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

    $request = (new class extends Request {
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

it('captures dotted query parameter keys', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('/users?key_1=value&key_2[sub_field]=value&key_3[]=value');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.query', ['key_1', 'key_2.sub_field', 'key_3.0']);
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

it('handles requests with no content-length header, such as chunked requests')->todo();

it('handles unknown routes')->todo(); // 'route' field

it('records authenticated user')->todo(); // 'user' field

it('handles streams')->todo(); // Content-Length

it('handles 304 Not Modified responses')->todo(); // Content-Length

it('handles HEAD requests')->todo(); // Content-Length

it('handles responses using Transfer-Encoding headers')->todo(); // Content-Length

it('captures query count')->todo(); // `queries` field + for the request of the execution context.

it('has a deploy_id fallback')->todo();

it('sorts the route methods')->todo(); // ?
it('sorts the query parameter keys')->todo(); // ?

it('handles streamed response sizes', function () {
    $ingest = fakeIngest();

    Route::get('test-route', function () {
        $file = new Stream(__FILE__);

        return response()->file($file);
    });

    get('test-route');

    $ingest->assertLatestWrite('requests.0.response_size_kilobytes', null);
});

class UserController
{
    public function index()
    {
        return [];
    }
}
