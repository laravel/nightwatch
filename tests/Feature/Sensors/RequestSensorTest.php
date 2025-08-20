<?php

namespace Tests\Feature\Sensors;

use App\Http\UserController;
use App\Livewire\Counter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Composer\InstalledVersions;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\SensorManager;
use Livewire\Livewire;
use Tests\TestCase;

use function fseek;
use function fwrite;
use function hash;
use function html_entity_decode;
use function json_decode;
use function now;
use function ob_end_clean;
use function ob_start;
use function preg_match;
use function preg_match_all;
use function report;
use function response;
use function stream_get_meta_data;
use function tap;
use function tmpfile;
use function version_compare;

class RequestSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_can_ingest_requests(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite([
            [
                'v' => 1,
                't' => 'request',
                'timestamp' => 946688523.456789,
                'deploy' => 'v1.2.3',
                'server' => 'web-01',
                '_group' => hash('xxh128', 'GET|HEAD,,/users'),
                'trace_id' => '00000000-0000-0000-0000-000000000000',
                'user' => '',
                'method' => 'GET',
                'url' => 'http://localhost/users',
                'route_name' => '',
                'route_methods' => ['GET', 'HEAD'],
                'route_domain' => '',
                'route_path' => '/users',
                'route_action' => 'Closure',
                'ip' => '127.0.0.1',
                'duration' => 0,
                'status_code' => 200,
                'request_size' => 0,
                'response_size' => 2,
                'bootstrap' => 0,
                'before_middleware' => 0,
                'action' => 0,
                'render' => 0,
                'after_middleware' => 0,
                'sending' => 0,
                'terminating' => 0,
                'exceptions' => 0,
                'logs' => 0,
                'queries' => 0,
                'lazy_loads' => 0,
                'jobs_queued' => 0,
                'mail' => 0,
                'notifications' => 0,
                'outgoing_requests' => 0,
                'files_read' => 0,
                'files_written' => 0,
                'cache_events' => 0,
                'hydrated_models' => 0,
                'peak_memory_usage' => 1234,
                'exception_preview' => '',
                'context' => Compatibility::$contextExists ? '{}' : '',
            ],
        ]);
    }

    public function test_it_captures_the_response_size(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => '[{"name":"Tim"}]');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.response_size', 16);
    }

    public function test_it_captures_the_response_size_of_a_streamed_file(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('users', fn () => response()->file($this->fixturePath('empty-array.json')));

        $response = $this->get('/users');

        $ingest->assertLatestWrite('request:0.response_size', 17);
    }

    public function test_it_gracefully_handles_response_size_for_a_streamed_file_that_is_deleted_after_sending_the_response(): void
    {
        // Testing this normally is hard. Laravel does not call `send` for
        // responses so we need to handle is pretty manually in this test.
        $ingest = $this->fakeIngest();
        $request = Request::create('http://localhost/users');

        $file = tmpfile();
        fwrite($file, '[{"name":"Tim"}]');
        fseek($file, 0);

        ob_start();
        $response = response()->file(stream_get_meta_data($file)['uri'])->deleteFileAfterSend()->sendContent();
        ob_end_clean();

        [$record, $resolver] = $this->core->sensor->request($request, $response);
        $this->core->ingest->write($resolver());
        $ingest->digest();

        $ingest->assertLatestWrite('request:0.response_size', 0);
    }

    public function test_it_gracefully_handles_response_size_for_streamed_responses(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('users', fn () => response()->stream(function (): void {
            echo '[]';
        }));

        $this->get('/users');

        $ingest->assertLatestWrite('request:0.response_size', 0);
    }

    public function test_it_captures_the_content_length_when_present_on_a_streamed_response_of_unknown_size(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('users', fn () => response()->stream(function (): void {
            echo '[]';
        }, headers: ['Content-length' => 2]));

        $this->get('/users');

        $ingest->assertLatestWrite('request:0.response_size', 2);
    }

    public function test_it_uses_the_content_length_header_as_the_response_size_when_present_on_a_streamed_file_response_where_the_file_is_deleted_after_sending(): void
    {
        $ingest = $this->fakeIngest();
        /** @var SensorManager */
        $request = Request::create('http://localhost/users');

        $file = tmpfile();
        fwrite($file, '[{"name":"Tim"}]');
        fseek($file, 0);

        ob_start();
        $response = response()->file(stream_get_meta_data($file)['uri'], headers: ['Content-length' => 17])->deleteFileAfterSend()->sendContent();
        ob_end_clean();

        [$record, $resolver] = $this->core->sensor->request($request, $response);
        $this->core->ingest->write($resolver());
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.response_size', 17);
    }

    public function test_it_captures_the_request_size(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->call('GET', '/users', content: 'abc');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.request_size', 3);
    }

    public function test_it_captures_the_authenticated_user(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->actingAs(new GenericUser(['id' => 'abc-123']))
            ->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.user', 'abc-123');
    }

    public function test_it_captures_query_parameters(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('/users?key_1=value&key_2[sub_field]=value&key_3[]=value&key_4[9]=value&key_5[][][foo][9]=bar&flag_value');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users?key_1=value&key_2[sub_field]=value&key_3[]=value&key_4[9]=value&key_5[][][foo][9]=bar&flag_value');
    }

    public function test_it_captures_the_route_name(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => [])->name('users.index');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.route_name', 'users.index');
    }

    public function test_it_captures_the_route_methods(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.route_methods', ['GET', 'HEAD']);
    }

    public function test_it_captures_route_actions_for_closures(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.route_action', 'Closure');
    }

    public function test_it_captures_route_actions_for_controller_classes(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', [UserController::class, 'index']);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.route_action', 'App\Http\UserController@index');
    }

    public function test_it_captures_real_path_and_route_path(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users/{user}', fn () => ['name' => 'Tim']);

        $response = $this->get('/users/123');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users/123');
        $ingest->assertLatestWrite('request:0.route_path', '/users/{user}');
    }

    public function test_it_captures_subdomain_and_route_domain(): void
    {
        $ingest = $this->fakeIngest();
        Route::domain('{product}.laravel.com')->get('/users/{user}', fn () => ['name' => 'Tim']);

        $response = $this->get('http://forge.laravel.com/users/123');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://forge.laravel.com/users/123');
        $ingest->assertLatestWrite('request:0.route_domain', '{product}.laravel.com');
    }

    public function test_it_doesnt_capture_the_request_ur_l_user(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('http://ryuta:secret@localhost/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
        $this->assertStringNotContainsString('ryuta', $ingest->latestWriteAsString());
        $this->assertStringNotContainsString('secret', $ingest->latestWriteAsString());
    }

    public function test_it_captures_the_duration_in_microseconds(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            $this->travelTo(now()->addMicroseconds(5));

            return [];
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (): void {
            report(new Exception('Handled error'));

            throw new Exception('Unhandled error');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.exceptions', 2);
        $ingest->assertLatestWrite('request:0.exception_preview', 'Unhandled error');
    }

    public function test_it_doesnt_capture_the_exception_preview_for_handled_exceptions(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            report(new Exception('Handled error'));

            return [];
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.exceptions', 1);
        $ingest->assertLatestWrite('request:0.exception_preview', '');
    }

    public function test_it_consistently_sorts_the_route_methods(): void
    {
        $ingest = $this->fakeIngest();
        Route::match(['GET', 'POST', 'PATCH'], '/users', fn () => []);
        Route::match(['PATCH', 'POST', 'GET'], '/users/{user}', fn () => []);

        $response = $this->get('/users');
        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.route_methods', ['GET', 'HEAD', 'PATCH', 'POST']);

        $response = $this->get('/users/123');
        $response->assertOk();
        $ingest->assertWrittenTimes(2);
        $ingest->assertLatestWrite('request:0.route_methods', ['GET', 'HEAD', 'PATCH', 'POST']);
    }

    public function test_it_handles_hea_d_requests(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->head('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.response_size', 0);
    }

    public function test_it_handles_204_no_content_requests(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => response('foo', 204));

        $response = $this->head('/users');

        $response->assertNoContent();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.response_size', 0);
    }

    public function test_it_captures_the_route_group(): void
    {
        $ingest = $this->fakeIngest();
        Route::domain('{product}.laravel.com')->get('/users/{user}', fn () => []);

        $response = $this->get('http://forge.laravel.com/users/123');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0._group', hash('xxh128', 'GET|HEAD,{product}.laravel.com,/users/{user}'));
    }

    public function test_it_handles_the_root_path(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/', fn () => 'Welcome');

        $response = $this->get('/');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.route_path', '/');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/');
    }

    public function test_it_gracefully_handles_non_string_query_string(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function (Request $request): void {
            $request->server->set('QUERY_STRING', []);
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_captures_bootstrap_execution_stage(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        // Simulating boot time.
        $this->core->stage(ExecutionStage::Bootstrap);
        $this->syncClock(now()->addMicroseconds(5));
        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.bootstrap', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_global_before_middleware_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        App::instance('travel-before', function ($request, $next) {
            $this->travelTo(now()->addMicroseconds(5));

            return $next($request);
        });
        $this->app[Kernel::class]->pushMiddleware('travel-before');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.before_middleware', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_route_before_middleware_duration(): void
    {
        $ingest = $this->fakeIngest();
        App::instance('travel-before', function ($request, $next) {
            $this->travelTo(now()->addMicroseconds(5));

            return $next($request);
        });
        Route::get('/users', fn () => [])->middleware('travel-before');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.before_middleware', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_action_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', function () {
            $this->travelTo(now()->addMicroseconds(5));

            return [];
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.action', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_render_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => new class implements Arrayable
        {
            public function toArray()
            {
                Date::setTestNow(now()->addMicroseconds(5));

                return [];
            }
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.render', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_route_after_middleware_duration(): void
    {
        $ingest = $this->fakeIngest();
        App::instance('travel-after', function ($request, $next) {
            return tap($next($request), function (): void {
                $this->travelTo(now()->addMicroseconds(5));
            });
        });
        Route::get('/users', fn () => [])->middleware('travel-after');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.after_middleware', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_global_after_middleware_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        App::instance('travel-after', function ($request, $next) {
            return tap($next($request), function (): void {
                $this->travelTo(now()->addMicroseconds(5));
            });
        });
        $this->app[Kernel::class]->pushMiddleware('travel-after');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.after_middleware', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_sending_duration(): void
    {
        $ingest = $this->fakeIngest();
        // When running tests, Laravel does not call the `send` method.  We will
        // call it here to simulate a real request as we want to make sure we
        // measure how long the request takes to send.
        Event::listen(fn (RequestHandled $event) => $event->response->send(true));
        Route::get('/users', fn () => response()->stream(function (): void {
            $this->travelTo(now()->addMicroseconds(5));

            // ...
        }));

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.sending', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_global_middleware_terminating_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        App::instance('terminable', new class
        {
            public function handle($request, $next)
            {
                return $next($request);
            }

            public function terminate(): void
            {
                Date::setTestNow(now()->addMicroseconds(5));
            }
        });
        $this->app[Kernel::class]->pushMiddleware('terminable');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.terminating', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_route_middleware_terminating_duration(): void
    {
        $ingest = $this->fakeIngest();
        App::instance('terminable', new class
        {
            public function handle($request, $next)
            {
                return $next($request);
            }

            public function terminate(): void
            {
                Date::setTestNow(now()->addMicroseconds(5));
            }
        });
        Route::get('/users', fn () => [])->middleware('terminable');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.exceptions', 0);
        $ingest->assertLatestWrite('request:0.terminating', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_terminating_callback_duration(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        App::terminating(function (): void {
            $this->travelTo(now()->addMicroseconds(5));
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.terminating', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_terminating_duration_for_unknown_routes(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        App::terminating(function (): void {
            $this->travelTo(now()->addMicroseconds(5));
        });

        $response = $this->get('/unknown');

        $response->assertNotFound();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.terminating', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_captures_middleware_duration_for_unknown_routes_and_collapses_after_middleware_into_before(): void
    {
        $ingest = $this->fakeIngest();
        App::instance('global-middleware', function ($request, $next) {
            $this->travelTo(now()->addMicroseconds(1));

            return tap($next($request), function (): void {
                $this->travelTo(now()->addMicroseconds(2));
            });
        });
        $this->app[Kernel::class]->pushMiddleware('global-middleware');

        $response = $this->get('/unknown');

        $response->assertNotFound();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.before_middleware', 3);
        $ingest->assertLatestWrite('request:0.after_middleware', 0);
        $ingest->assertLatestWrite('request:0.duration', 3);
    }

    public function test_it_captures_middleware_durations_for_global_middleware_that_return_a_response_and_it_collapses_after_middleware_into_before(): void
    {
        $ingest = $this->fakeIngest();
        App::instance('global-middleware-change-response', function ($request, $next) {
            $this->travelTo(now()->addMicroseconds(1));

            return response('');
        });
        App::instance('global-middleware-progress-time', function ($request, $next) {
            $this->travelTo(now()->addMicroseconds(2));

            return tap($next($request), function (): void {
                $this->travelTo(now()->addMicroseconds(3));
            });
        });
        $this->app[Kernel::class]->pushMiddleware('global-middleware-progress-time');
        $this->app[Kernel::class]->pushMiddleware('global-middleware-change-response');
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.before_middleware', 6);
        $ingest->assertLatestWrite('request:0.after_middleware', 0);
        $ingest->assertLatestWrite('request:0.duration', 6);
    }

    public function test_it_captures_the_render_duration_for_responses_returned_from_a_middleware_as_part_of_the_middleware_stage(): void
    {
        $ingest = $this->fakeIngest();
        App::instance('renderable-response-middleware', fn ($request, $next) => new class implements Arrayable
        {
            public function toArray()
            {
                Date::setTestNow(now()->addMicroseconds(5));

                return [];
            }
        });
        Route::get('/users', fn () => [])->middleware('renderable-response-middleware');

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.before_middleware', 5);
        $ingest->assertLatestWrite('request:0.duration', 5);
    }

    public function test_it_supports_custom_request_methods(): void
    {
        $ingest = $this->fakeIngest();
        Route::match('blah', '/', fn () => 'Welcome!');

        $response = $this->call('blah', '/');

        $response->assertOk();
        $response->assertContent('Welcome!');
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.method', 'BLAH');
        $ingest->assertLatestWrite('request:0.route_methods', ['BLAH']);
    }

    public function test_it_captures_context(): void
    {
        $this->markTestSkippedUnless(Compatibility::$contextExists, 'This test requires the Laravel Context.');

        $ingest = $this->fakeIngest();
        $model = User::factory()->create();
        Route::get('/test', function () use ($model) {
            Context::add('string', 'value');
            Context::add('integer', 123);
            Context::add('float', 123.456);
            Context::add('boolean', true);
            Context::add('null', null);
            Context::add('list', [1, 2.0, 'three']);
            Context::add('associative', ['key' => 'value']);
            Context::add('object', (object) ['key' => 'value']);
            Context::add('model', $model);
        });

        $response = $this->get('/test');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.context', function ($context) use ($model) {
            $context = json_decode($context, true);
            $this->assertSame('value', $context['string']);
            $this->assertSame(123, $context['integer']);
            $this->assertSame(123.456, $context['float']);
            $this->assertTrue($context['boolean']);
            $this->assertNull($context['null']);
            $this->assertSame([1, 2.0, 'three'], $context['list']);
            $this->assertSame(['key' => 'value'], $context['associative']);
            $this->assertSame(['key' => 'value'], $context['object']);
            $this->assertSame($model->getKey(), $context['model']['id']);

            return true;
        });
    }

    public function test_livewire_2(): void
    {
        $this->markTestSkippedWhen(version_compare(InstalledVersions::getVersion('livewire/livewire'), '3.0.0', '>='), 'Requires Livewire 2');

        $ingest = $this->fakeIngest();
        Config::set('livewire.class_namespace', 'App\\Livewire'); // This is the default for Livewire 3, but we set it here to ensure compatibility with Livewire 2.
        Route::get('/counter', Counter::class);

        $response = $this
            ->get('/counter')
            ->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/counter');
        $ingest->assertLatestWrite('request:0.route_path', '/counter');
        $ingest->assertLatestWrite('request:0.route_action', 'App\Livewire\Counter');

        $ingest->forgetWrites();
        $this->core->prepareForNextRequest();

        preg_match('/wire:initial-data="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = json_decode(html_entity_decode($matches[1]), true);

        $response = $this
            ->withHeader('X-Livewire', true)
            ->post('/livewire/message/counter', [
                'fingerprint' => $snapshot['fingerprint'],
                'serverMemo' => $snapshot['serverMemo'],
                'updates' => [
                    [
                        'type' => 'syncInput',
                        'payload' => [
                            'name' => 'count',
                            'value' => 2,
                        ],
                    ],
                    [
                        'type' => 'callMethod',
                        'payload' => [
                            'id' => 'foo',
                            'method' => 'increment',
                            'params' => [],
                        ],
                    ],
                    [
                        'type' => 'callMethod',
                        'payload' => [
                            'id' => 'foo',
                            'method' => 'increment',
                            'params' => [],
                        ],
                    ],
                    [
                        'type' => 'callMethod',
                        'payload' => [
                            'id' => 'foo',
                            'method' => 'decrement',
                            'params' => [],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/livewire/message/counter');
        $ingest->assertLatestWrite('request:0.route_path', '/livewire/message/{name}');
        $ingest->assertLatestWrite('request:0.route_action', 'App\Livewire\Counter');
    }

    public function test_livewire_3(): void
    {
        $this->markTestSkippedWhen(version_compare(InstalledVersions::getVersion('livewire/livewire'), '3.0.0', '<'), 'Requires Livewire 3');

        $ingest = $this->fakeIngest();
        Route::get('/counter', Counter::class);

        $response = $this
            ->get('/counter')
            ->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/counter');
        $ingest->assertLatestWrite('request:0.route_path', '/counter');
        $ingest->assertLatestWrite('request:0.route_action', 'App\Livewire\Counter');

        $ingest->forgetWrites();
        $this->core->prepareForNextRequest();

        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = html_entity_decode($matches[1]);

        $response = $this
            ->withHeader('X-Livewire', true)
            ->post('/livewire/update', [
                'components' => [
                    [
                        'snapshot' => $snapshot,
                        'updates' => [
                            'count' => 2,
                        ],
                        'calls' => [
                            [
                                'method' => 'increment',
                                'params' => [],
                            ],
                            [
                                'method' => 'increment',
                                'params' => [],
                            ],
                            [
                                'method' => 'decrement',
                                'params' => [],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/livewire/update');
        $ingest->assertLatestWrite('request:0.route_path', '/livewire/update');
        $ingest->assertLatestWrite('request:0.route_action', 'App\Livewire\Counter');
    }

    public function test_livewire_3_with_multiple_components(): void
    {
        $this->markTestSkippedWhen(version_compare(InstalledVersions::getVersion('livewire/livewire'), '3.0.0', '<'), 'Requires Livewire 3');

        $ingest = $this->fakeIngest();
        Route::view('/dashboard', 'dashboard');

        $response = $this
            ->get('/dashboard')
            ->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/dashboard');
        $ingest->assertLatestWrite('request:0.route_path', '/dashboard');
        $ingest->assertLatestWrite('request:0.route_action', '\Illuminate\Routing\ViewController');

        $ingest->forgetWrites();
        $this->core->prepareForNextRequest();

        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot1 = html_entity_decode($matches[1][0]);
        $snapshot2 = html_entity_decode($matches[1][1]);

        $response = $this
            ->withHeader('X-Livewire', true)
            ->post('/livewire/update', [
                'components' => [
                    [
                        'snapshot' => $snapshot1,
                        'updates' => [
                            'count' => 2,
                        ],
                        'calls' => [
                            [
                                'method' => 'increment',
                                'params' => [],
                            ],
                            [
                                'method' => 'increment',
                                'params' => [],
                            ],
                            [
                                'method' => 'decrement',
                                'params' => [],
                            ],
                        ],
                    ],
                    [
                        'snapshot' => $snapshot2,
                        'updates' => [
                            'count' => 2,
                        ],
                        'calls' => [],
                    ],
                ],
            ])
            ->assertOk();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/livewire/update');
        $ingest->assertLatestWrite('request:0.route_path', '/livewire/update');
        $ingest->assertLatestWrite('request:0.route_action', 'App\Livewire\Counter, App\Livewire\AnotherCounter');
    }
}
