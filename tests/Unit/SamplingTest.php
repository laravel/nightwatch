<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Models\User as UserModel;
use App\Notifications\MyNotification;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Records\User;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

use function app;
use function collect;
use function defer;
use function function_exists;
use function json_decode;
use function microtime;
use function report;
use function request;
use function response;

class SamplingTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_can_configure_request_sampling(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertSame(0, $sampled);

        $this->core->config['sampling']['requests'] = 0.25;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(250, $sampled, 50);

        $this->core->config['sampling']['requests'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(500, $sampled, 50);

        $this->core->config['sampling']['requests'] = 1.0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertSame(1000, $sampled);
    }

    public function test_it_samples_queries(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            DB::table('users')->get();
        }

        $this->assertSame(0, $this->core->executionState->queries);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            DB::table('users')->get();
        }

        $this->assertSame(10, $this->core->executionState->queries);
    }

    public function test_it_can_capture_queries_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            UserModel::all();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from "users"');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_set_sample_rate_to_capture_events_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;

        $this->core->config['sampling']['exceptions'] = 0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(0, $sampled);

        $this->core->config['sampling']['exceptions'] = 0.25;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(250, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(500, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 0.75;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta(750, $sampled, 50);

        $this->core->config['sampling']['exceptions'] = 1.0;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');

            if ($this->core->shouldSampleOnException) {
                $sampled++;
            }
        }

        $this->assertSame(1000, $sampled);
    }

    public function test_it_captures_events_following_an_exception_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            app()->terminating(function () {
                for ($i = 0; $i < 1_000; $i++) {
                    UserModel::all();
                }
            });

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(3);
        $ingest->assertWrite(0, function ($records) {
            $this->assertCount(500, $records);

            return true;
        });
        $ingest->assertWrite(0, 'exception:0.message', 'Whoops!');
        for ($i = 0; $i < 499; $i++) {
            $ingest->assertWrite(0, "query:{$i}.sql", 'select * from "users"');
        }
        $ingest->assertWrite(1, function ($records) {
            $this->assertCount(500, $records);

            return true;
        });
        for ($i = 0; $i < 500; $i++) {
            $ingest->assertWrite(1, "query:{$i}.sql", 'select * from "users"');
        }
        $ingest->assertWrite(2, function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertWrite(2, 'query:0.sql', 'select * from "users"');
        $ingest->assertWrite(2, 'request:0.url', 'http://localhost/users');
    }

    public function test_it_captures_events_after_an_exception_is_reported_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            UserModel::get();

            report(new RuntimeException('Whoops!'));
        });

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from "users"');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_notifications(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        }

        $this->assertSame(0, $this->core->executionState->notifications);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        }

        $this->assertSame(10, $this->core->executionState->notifications);
    }

    public function test_it_can_capture_notifications_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('notification:0.class', MyNotification::class);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_mail(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Mail::to('tim@laravel.com')->send(new MyMail);
        }

        $this->assertSame(0, $this->core->executionState->mail);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Mail::to('tim@laravel.com')->send(new MyMail);
        }

        $this->assertSame(10, $this->core->executionState->mail);
    }

    public function test_it_can_capture_mail_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            Mail::to('tim@laravel.com')->send(new MyMail);

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('mail:0.subject', 'Welcome');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_cache(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Cache::get('foo');
        }

        $this->assertSame(0, $this->core->executionState->cacheEvents);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Cache::get('foo');
        }

        $this->assertSame(10, $this->core->executionState->cacheEvents);
    }

    public function test_it_can_capture_cache_events_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            Cache::get('foo');

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('cache-event:0.key', 'foo');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_exceptions(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            report('Whoops!');
        }

        $this->assertSame(0, $this->core->executionState->exceptions);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            report('Whoops!');
        }

        $this->assertSame(10, $this->core->executionState->exceptions);
    }

    public function test_it_samples_queued_jobs(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            MyJob::dispatch();
        }

        $this->assertSame(0, $this->core->executionState->jobsQueued);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            MyJob::dispatch();
        }

        $this->assertSame(10, $this->core->executionState->jobsQueued);
    }

    public function test_it_can_capture_queued_jobs_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            MyJob::dispatch();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(4, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('queued-job:0.name', MyJob::class);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_outgoing_requests(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);

        for ($i = 0; $i < 10; $i++) {
            Http::get('https://nightwatch.laravel.com');
        }

        $this->assertSame(0, $this->core->executionState->outgoingRequests);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Http::get('https://nightwatch.laravel.com');
        }

        $this->assertSame(10, $this->core->executionState->outgoingRequests);
    }

    public function test_it_can_capture_outgoing_requests_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;
        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);

        Route::get('/users', function () {
            Http::get('https://nightwatch.laravel.com');

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('outgoing-request:0.url', 'https://nightwatch.laravel.com');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_stage(): void
    {
        $this->core->stage(ExecutionStage::Bootstrap);
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        $this->core->stage(ExecutionStage::Render);

        $this->assertSame(ExecutionStage::Bootstrap, $this->core->executionState->stage);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        $this->core->stage(ExecutionStage::Render);

        $this->assertSame(ExecutionStage::Render, $this->core->executionState->stage);
    }

    public function test_it_can_capture_stages_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $this->freezeTime();
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            $this->travel(9)->seconds();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
        $ingest->assertLatestWrite('request:0.action', 9_000_000);
    }

    public function test_it_samples_remembering_user(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');
        $user = new GenericUser(['id' => 123, 'remember_token' => '']);

        Auth::login($user);
        Auth::logout();

        $this->assertSame('', $this->core->executionState->user->id()->jsonSerialize());

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        Auth::login($user);
        Auth::logout();

        $this->assertSame('123', $this->core->executionState->user->id()->jsonSerialize());
    }

    public function test_it_can_capture_logged_out_user_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $this->freezeTime();
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;
        $user = new GenericUser(['id' => 123, 'remember_token' => '']);

        Route::get('/logout', function () {
            Auth::logout();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->actingAs($user)->get('/logout');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('user:0.id', '123');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/logout');
    }

    public function test_it_can_capture_logged_in_user_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $this->freezeTime();
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/logout', function () {
            Auth::login(new GenericUser(['id' => 123, 'remember_token' => '']));

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/logout');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('user:0.id', '123');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/logout');
    }

    public function test_it_samples_user(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->configureSampling('requests');
        Auth::login(new GenericUser(['id' => 123, 'remember_token' => '']));

        for ($i = 0; $i < 10; $i++) {
            $this->core->captureUser();
        }

        $this->assertSame('[]', $this->core->ingest->buffer->pull()->rawPayload());

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            $this->core->captureUser();
        }

        $users = collect(json_decode($this->core->ingest->buffer->pull()->rawPayload()));
        $this->assertCount(10, $users);
        $this->assertTrue($users->pluck('id')->every(fn ($id) => $id === '123'));
    }

    public function test_it_samples_requests(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->configureSampling('requests');
        $request = Request::create('https://laravel.com');
        $response = new Response;

        for ($i = 0; $i < 10; $i++) {
            $this->core->request($request, $response);
        }

        $this->assertSame('[]', $this->core->ingest->buffer->pull()->rawPayload());

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            $this->core->request($request, $response);
        }

        $requests = collect(json_decode($this->core->ingest->buffer->pull()->rawPayload()));
        $this->assertCount(10, $requests);
        $this->assertTrue($requests->pluck('url')->every(fn ($url) => $url === 'https://laravel.com/'));
    }

    public function test_it_can_capture_requests_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            if (function_exists('defer')) {
                defer(fn () => throw new RuntimeException('Whoops!'), always: true);
            } else {
                throw new RuntimeException('Whoops!');
            }

            return response(status: 500);
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_logs(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Log::channel('nightwatch')->info('Hello world');
        }

        $this->assertSame(0, $this->core->executionState->logs);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            Log::channel('nightwatch')->info('Hello world');
        }

        $this->assertSame(10, $this->core->executionState->logs);
    }

    public function test_it_can_capture_logs_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            Log::channel('nightwatch')->info('Hello world');

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('log:0.message', 'Hello world');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    #[DataProvider('routeMiddleware')]
    public function test_it_does_not_attach_route_middleware_when_not_sampling(bool $terminatingEventExists, array $expectedMiddleware): void
    {
        Compatibility::$terminatingEventExists = $terminatingEventExists;
        $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0.0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');
        $middleware = [];
        Route::get('/test', function () use (&$middleware): void {
            $middleware = request()->route()->middleware();
        });

        for ($i = 0; $i < 10; $i++) {
            $this->get('test')->assertOk();

            $this->assertSame([], $middleware);
        }

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        for ($i = 0; $i < 10; $i++) {
            $this->get('test')->assertOk();

            $this->assertSame($expectedMiddleware, $middleware);
        }
    }

    public static function routeMiddleware(): iterable
    {
        yield [true, [RouteMiddleware::class]];
        yield [false, [GlobalMiddleware::class, RouteMiddleware::class]];
    }

    public function test_it_samples_capturing_request_preview(): void
    {
        $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0.0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');
        Route::get('/test', function (): void {
            //
        });

        $this->get('test')->assertOk();

        $this->assertSame('', $this->core->executionState->executionPreview);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');
        $this->app->forgetScopedInstances();

        $this->get('test')->assertOk();

        $this->assertSame('GET /test', $this->core->executionState->executionPreview);
    }

    public function test_it_can_capture_execution_preview_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            UserModel::all();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from "users"');
        $ingest->assertLatestWrite('query:0.execution_preview', 'GET /users');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('exception:0.execution_preview', 'GET /users');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_samples_ingest(): void
    {
        $ingest = $this->fakeIngest();

        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $this->core->configureSampling('requests');
        $this->core->ingest->write(new User(
            timestamp: microtime(true),
            id: '123',
            name: '',
            username: '',
        ));
        $this->core->digest();

        $this->assertCount(1, $this->core->ingest->buffer);
        $ingest->assertWrittenTimes(0);

        $this->core->config['sampling']['requests'] = 1;
        $this->core->configureSampling('requests');
        $this->core->ingest->write(new User(
            timestamp: microtime(true),
            id: '123',
            name: '',
            username: '',
        ));
        $this->core->digest();

        $this->assertCount(0, $this->core->ingest->buffer);
        $ingest->assertWrittenTimes(1);
    }

    public function test_it_flushes_ingest_after_request_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            UserModel::all();

            return $this->core->ingest->buffer->count();
        });

        $response = $this->get('/users');

        $response->assertOk()
            ->assertContent('1');
        $ingest->assertWrittenTimes(0);
        $this->assertCount(0, $this->core->ingest->buffer);
    }

    public function test_it_discards_records_captured_before_sampling_rate_decided(): void
    {
        DB::table('users')->get();
        $this->core->config['sampling']['requests'] = 0.0;
        $this->core->config['sampling']['exceptions'] = 0.0;
        $count = null;
        Route::get('/test', function () use (&$count): void {
            $count = $this->core->ingest->buffer->count();
        });

        $this->get('test')->assertOk();

        $this->assertSame(0, $count);
    }

    public function test_it_captures_records_captured_before_sampling_rate_decided_after_exception_occurs_when_not_sampling_unless_exception_occurs(): void
    {
        DB::table('users')->get();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;
        $count = null;
        Route::get('/test', function () use (&$count): void {
            $count = $this->core->ingest->buffer->count();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('test');

        $response->assertServerError();
        $this->assertSame(1, $count);
    }

    public function test_it_discards_records_over_the_buffer_threshold_when_not_sampling_unless_exception_occurs(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            for ($i = 0; $i < 1_000; $i++) {
                UserModel::all();
            }

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(2);
        $ingest->assertWrite(0, function ($records) {
            $this->assertCount(500, $records);

            return true;
        });
        for ($i = 0; $i < 499; $i++) {
            $ingest->assertWrite(0, "query:{$i}.sql", 'select * from "users"');
        }
        $ingest->assertWrite(0, 'exception:0.message', 'Whoops!');
        $ingest->assertWrite(1, function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertWrite(1, 'request:0.url', 'http://localhost/users');
    }

    public function test_it_adds_context_for_job_sampling(): void
    {
        $this->core->config['sampling']['requests'] = 0;
        $this->core->configureSampling('requests');

        $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

        $this->assertFalse($shouldSample);

        $this->core->config['sampling']['requests'] = 1;
        $this->core->configureSampling('requests');

        $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

        $this->assertTrue($shouldSample);
    }

    public function test_dispatched_job_executions_are_not_sampled_if_dispatched_after_exception_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            MyJob::dispatch();

            $this->assertFalse(Compatibility::getHiddenContext('nightwatch_should_sample'));

            app()->terminating(fn () => MyJob::dispatch());

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');
        $jobs = DB::table('jobs')->get();

        $response->assertServerError();
        $this->assertFalse(Compatibility::getHiddenContext('nightwatch_should_sample'));
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(6, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('query:1.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('queued-job:0.name', MyJob::class);
        $ingest->assertLatestWrite('queued-job:1.name', MyJob::class);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.exception_preview', 'Whoops!');
        $this->assertCount(2, $jobs);
        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_should_sample":"b:0;"', $jobs[0]->payload);
            $this->assertStringContainsString('"nightwatch_should_sample":"b:0;"', $jobs[1]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":"b:1;"', $jobs[0]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":"b:1;"', $jobs[1]->payload);
        } else {
            $this->assertStringContainsString('"nightwatch_should_sample":false', $jobs[0]->payload);
            $this->assertStringContainsString('"nightwatch_should_sample":false', $jobs[1]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":true', $jobs[0]->payload);
            $this->assertStringNotContainsString('"nightwatch_should_sample":true', $jobs[1]->payload);
        }
    }

    public function test_captured_request_gets_exception_preview_after_exception_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0;
        $this->core->config['sampling']['exceptions'] = 1.0;

        Route::get('/users', function () {
            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $response->assertServerError();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.exception_preview', 'Whoops!');
    }
}
