<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\ExecutionPhase;
use Laravel\Nightwatch\SensorManager;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\head;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    setDeployId('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    syncClock(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
});

it('can ingest requests', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);
    /** @var SensorManager */
    $sensor = app(SensorManager::class);
    $sensor->prepareForNextInvocation();
    syncClock(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    $sensor->start(ExecutionPhase::Bootstrap);

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests', [
        [
            'v' => 1,
            'timestamp' => 946688523.456789,
            'deploy_id' => 'v1.2.3',
            'server' => 'web-01',
            'group' => hash('md5', 'GET|HEAD,,/users'),
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
            'peak_memory_usage' => 1234,
            'bootstrap' => 0,
            'before_middleware' => 0,
            'action' => 0,
            'render' => 0,
            'after_middleware' => 0,
            'sending' => 0,
            'terminating' => 0,
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
    /** @var SensorManager */
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
    /** @var SensorManager */
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
    /** @var SensorManager */
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

it('creates route group', function () {
    $ingest = fakeIngest();
    Route::domain('{product}.laravel.com')->get('/users/{user}', fn () => []);

    $response = get('http://forge.laravel.com/users/123');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.group', hash('md5', 'GET|HEAD,{product}.laravel.com,/users/{user}'));
});

it('captures the root route path correctly', function () {
    $ingest = fakeIngest();
    Route::get('/', fn () => 'Welcome');

    $response = get('/');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.route_path', '/');
    $ingest->assertLatestWrite('requests.0.path', '/');
});

it('captures execution phase durations', function () {
    $ingest = fakeIngest();
    $sensor = app(SensorManager::class);
    app(Kernel::class)->setGlobalMiddleware([
        ...app(Kernel::class)->getGlobalMiddleware(),
        TravelMicrosecondsMiddleware::class.':2,10', // global middleware before / after
    ]);
    Event::listen(function (RequestHandled $event) {
        $event->response->send(true);
    });

    Route::get('/users', function () {
        travelTo(now()->addMicroseconds(5)); // main

        App::terminating(function () {
            travelTo(now()->addMicroseconds(89)); // main
        });

        return new class implements Responsable
        {
            public function toResponse($request)
            {
                travelTo(now()->addMicroseconds(8)); // main_render

                return response('main response');
            }
        };
    })->middleware([ChangeRouteResponse::class.':3,55', TravelMicrosecondsMiddleware::class.':1,3']); // route middleware before / after

    $sensor->prepareForNextInvocation();
    syncClock(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    travelTo(now()->addMicroseconds(1));
    $sensor->start(ExecutionPhase::BeforeMiddleware);
    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.bootstrap', 1);
    $ingest->assertLatestWrite('requests.0.before_middleware', 3);
    $ingest->assertLatestWrite('requests.0.action', 5);
    $ingest->assertLatestWrite('requests.0.render', 8);
    $ingest->assertLatestWrite('requests.0.after_middleware', 16);
    $ingest->assertLatestWrite('requests.0.sending', 55);
    $ingest->assertLatestWrite('requests.0.terminating', 89);
    $ingest->assertLatestWrite('requests.0.duration', 1 + 3 + 5 + 8 + 16 + 55 + 89);
});

it('forces query to be a string', function () {
    $ingest = fakeIngest();
    Route::get('/users', function (Request $request) {
        $request->server->set('QUERY_STRING', []);
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('requests.0.query', '');
});

final class UserController
{
    public function index()
    {
        return [];
    }
}

final class TravelMicrosecondsMiddleware
{
    public function handle(Request $request, Closure $next, int $beforeMicroseconds, int $afterMicroseconds): mixed
    {
        travelTo(now()->addMicroseconds($beforeMicroseconds));

        $response = $next($request);

        travelTo(now()->addMicroseconds($afterMicroseconds));

        return $response;
    }
}

final class ChangeRouteResponse
{
    public function handle(Request $request, Closure $next, int $middlewareDuration, int $responseDuration): mixed
    {
        $next($request);

        return new class($middlewareDuration, $responseDuration) extends StreamedResponse
        {
            public function __construct(private $middlewareDuration, private $responseDuration)
            {
                parent::__construct(function () {
                    travelTo(now()->addMicroseconds($this->responseDuration)); // route_after_middleware_render

                    // echo 'output';
                });
            }

            public function prepare(HttpFoundationRequest $request): static
            {
                travelTo(now()->addMicroseconds($this->middlewareDuration)); // after_middleware

                return parent::prepare($request);
            }
        };
    }
}
