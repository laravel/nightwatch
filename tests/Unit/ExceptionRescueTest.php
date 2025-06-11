<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Models\User;
use App\Notifications\MyNotification;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

use function app;
use function defer;

class ExceptionRescueTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    // ----
    // When a request is not being sampled...
    // If an exception occurs (or is reported?)...
    // Previous unfiltered events should be captured...
    // All future unfiltered events should be captured...
    // The execution container should be captured...
    // The sampling state should not changed for dispatched jobs.
    // ----
    public function test_it_can_capture_queries_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            User::all();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from "users"');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_capture_notifications_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('notification:0.class', MyNotification::class);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_capture_mail_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            Mail::to('tim@laravel.com')->send(new MyMail);

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('mail:0.class', MyMail::class);
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_capture_cache_events_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            Cache::get('foo');

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('cache-event:0.key', 'foo');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_capture_queued_jobs_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            MyJob::dispatch();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

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

    public function test_it_can_capture_outgoing_requests_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;
        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);

        Route::get('/users', function () {
            Http::get('https://nightwatch.laravel.com');

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('outgoing-request:0.url', 'https://nightwatch.laravel.com');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_capture_stages_after_exception_occurs_when_not_sampling(): void
    {
        $this->freezeTime();
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            $this->travel(9)->seconds();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
        $ingest->assertLatestWrite('request:0.action', 9_000_000);
    }

    public function test_it_can_capture_logged_out_user_after_exception_occurs_when_not_sampling(): void
    {
        $this->freezeTime();
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;
        $user = new GenericUser(['id' => 123, 'remember_token' => '']);

        Route::get('/logout', function () {
            Auth::logout();

            throw new RuntimeException('Whoops!');
        });

        $response = $this->actingAs($user)->get('/logout');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('user:0.id', '123');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/logout');
    }

    public function test_it_can_capture_logged_in_user_after_exception_occurs_when_not_sampling(): void
    {
        $this->freezeTime();
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/logout', function () {
            Auth::login(new GenericUser(['id' => 123, 'remember_token' => '']));

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/logout');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('user:0.id', '123');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/logout');
    }

    public function test_it_can_capture_logs_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            Log::channel('nightwatch')->info('Hello world');

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('log:0.message', 'Hello world');
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_can_capture_requests_after_exception_occurs_when_not_sampling(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            defer(fn () => throw new RuntimeException('Whoops!'));
        });

        $response = $this->get('/users');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/users');
    }

    public function test_it_will_discard_records_over_the_buffer_threshold(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            for ($i = 0; $i < 1_000; $i++) {
                User::all();
            }

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

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

    public function test_it_captures_all_events_following_an_exception(): void
    {
        $ingest = $this->fakeIngest();
        $this->core->config['sampling']['always_exceptions'] = true;
        $this->core->config['sampling']['requests'] = 0;

        Route::get('/users', function () {
            app()->terminating(function () {
                for ($i = 0; $i < 1_000; $i++) {
                    User::all();
                }
            });

            throw new RuntimeException('Whoops!');
        });

        $response = $this->get('/users');

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
}
