<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Jobs\SampledJob;
use App\Mail\MyMail;
use App\Notifications\MyNotification;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Command;
use Laravel\Nightwatch\Records\Mail as MailRecord;
use Laravel\Nightwatch\Records\Notification as NotificationRecord;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Laravel\Nightwatch\Records\QueuedJob;
use Laravel\Nightwatch\Records\Request;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Tests\TestCase;

use function array_shift;
use function preg_replace;
use function report;
use function str_contains;
use function str_replace;

class FilteringTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();
    }

    public function test_it_can_ignore_queries(): void
    {
        $this->core->config['filtering']['ignore_queries'] = true;

        for ($i = 0; $i < 10; $i++) {
            DB::table('users')->get();
        }

        $this->assertSame(0, $this->core->executionState->queries);

        $this->core->config['filtering']['ignore_queries'] = false;

        for ($i = 0; $i < 10; $i++) {
            DB::table('users')->get();
        }

        $this->assertSame(10, $this->core->executionState->queries);
    }

    public function test_it_can_filter_queries(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::rejectQueries(fn (Query $query) => str_contains($query->sql, 'jobs'));
        Nightwatch::rejectQueries(fn (Query $query) => str_contains($query->sql, 'sessions'));

        DB::statement('select * from users');
        DB::statement('select * from jobs');
        DB::statement('select * from sessions');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
    }

    public function test_it_records_queries_when_null_is_returned(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::rejectQueries(function (Query $query) {
            //
        });

        DB::statement('select * from users');
        DB::statement('select * from jobs');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
        $ingest->assertLatestWrite('query:1.sql', 'select * from jobs');
    }

    public function test_it_can_ignore_notifications(): void
    {
        $this->core->config['filtering']['ignore_notifications'] = true;

        for ($i = 0; $i < 10; $i++) {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        }

        $this->assertSame(0, $this->core->executionState->notifications);

        $this->core->config['filtering']['ignore_notifications'] = false;

        for ($i = 0; $i < 10; $i++) {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        }

        $this->assertSame(10, $this->core->executionState->notifications);
    }

    public function test_it_can_filter_notifications(): void
    {
        $ingest = $this->fakeIngest();
        $reject = [true, true, false];
        Nightwatch::rejectNotifications(function (NotificationRecord $notification) use (&$reject) {
            return array_shift($reject);
        });

        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('notification:0.class', MyNotification::class);
    }

    public function test_it_can_ignore_mail(): void
    {
        $this->core->config['filtering']['ignore_mail'] = true;

        for ($i = 0; $i < 10; $i++) {
            Mail::to('tim@laravel.com')->send(new MyMail);
        }

        $this->assertSame(0, $this->core->executionState->mail);

        $this->core->config['filtering']['ignore_mail'] = false;

        for ($i = 0; $i < 10; $i++) {
            Mail::to('tim@laravel.com')->send(new MyMail);
        }

        $this->assertSame(10, $this->core->executionState->mail);
    }

    public function test_it_can_filter_mail(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::rejectMail(fn (MailRecord $mail) => $mail->subject === 'Hello Laravel');
        Nightwatch::rejectMail(fn (MailRecord $mail) => $mail->subject === 'Hello Cloud');

        Mail::to('tim@laravel.com')->send(new MyMail('Hello Laravel'));
        Mail::to('tim@laravel.com')->send(new MyMail('Hello Cloud'));
        Mail::to('tim@laravel.com')->send(new MyMail('Hello Nightwatch'));
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('mail:0.subject', 'Hello Nightwatch');
    }

    public function test_it_can_ignore_cache_events(): void
    {
        $this->core->config['filtering']['ignore_cache_events'] = true;

        for ($i = 0; $i < 10; $i++) {
            Cache::get('foo');
        }

        $this->assertSame(0, $this->core->executionState->cacheEvents);

        $this->core->config['filtering']['ignore_cache_events'] = false;

        for ($i = 0; $i < 10; $i++) {
            Cache::get('foo');
        }

        $this->assertSame(10, $this->core->executionState->cacheEvents);
    }

    public function test_it_can_filter_cache_events(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::rejectCacheEvents(fn (CacheEvent $cacheEvent) => str_contains($cacheEvent->key, 'forget'));
        Nightwatch::rejectCacheEvents(fn (CacheEvent $cacheEvent) => str_contains($cacheEvent->key, 'remember'));

        Cache::get('keep');
        Cache::get('forget');
        Cache::get('remember');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('cache-event:0.key', 'keep');
    }

    public function test_it_can_ignore_outgoing_requests(): void
    {
        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);

        $this->core->config['filtering']['ignore_outgoing_requests'] = true;

        for ($i = 0; $i < 10; $i++) {
            Http::get('https://nightwatch.laravel.com');
        }

        $this->assertSame(0, $this->core->executionState->outgoingRequests);

        $this->core->config['filtering']['ignore_outgoing_requests'] = false;

        for ($i = 0; $i < 10; $i++) {
            Http::get('https://nightwatch.laravel.com');
        }

        $this->assertSame(10, $this->core->executionState->outgoingRequests);
    }

    public function test_it_can_filter_outgoing_requests(): void
    {
        $ingest = $this->fakeIngest();
        Http::fake([
            'https://laravel.com' => Http::response(status: 200),
            'https://laravel.cloud' => Http::response(status: 200),
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);
        Nightwatch::rejectOutgoingRequests(fn (OutgoingRequest $outgoingRequest) => $outgoingRequest->url === 'https://laravel.cloud');
        Nightwatch::rejectOutgoingRequests(fn (OutgoingRequest $outgoingRequest) => $outgoingRequest->url === 'https://nightwatch.laravel.com');

        Http::get('https://laravel.com');
        Http::get('https://laravel.cloud');
        Http::get('https://nightwatch.laravel.com');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('outgoing-request:0.host', 'laravel.com');
    }

    public function test_it_can_filter_queued_jobs(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::rejectQueuedJobs(function (QueuedJob $queuedJob) {
            return $queuedJob->name === MyJob::class;
        });

        SampledJob::dispatch(1);
        MyJob::dispatch();
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(3, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:*', function ($queries) {
            $this->assertCount(2, $queries);

            return true;
        });
        $ingest->assertLatestWrite('queued-job:0.name', SampledJob::class);
    }

    public function test_it_does_not_trigger_recursion_while_filtering()
    {
        $ingest = $this->fakeIngest();
        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);
        Nightwatch::rejectQueries(function () {
            DB::statement('select * from users');

            return true;
        });
        Nightwatch::rejectQueuedJobs(function () {
            MyJob::dispatch();

            return true;
        });
        Nightwatch::rejectOutgoingRequests(function () {
            Http::get('https://nightwatch.laravel.com');

            return true;
        });
        Nightwatch::rejectNotifications(function (NotificationRecord $notification) use (&$keep) {
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);

            return true;
        });
        Nightwatch::rejectMail(function (MailRecord $mail) {
            Mail::to('tim@laravel.com')->send(new MyMail('Hello Laravel'));

            return true;
        });
        Nightwatch::rejectCacheEvents(function (CacheEvent $cacheEvent) {
            Cache::get('keep');

            return true;
        });

        DB::statement('select * from users');
        MyJob::dispatch();
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
        Mail::to('tim@laravel.com')->send(new MyMail('Hello Laravel'));
        Cache::get('keep');
        Http::get('https://nightwatch.laravel.com');
        $ingest->digest();

        $ingest->assertWrittenTimes(0);
    }

    public function test_it_can_ignore_events(): void
    {
        $ingest = $this->fakeIngest();
        Http::fake([
            'https://nightwatch.laravel.com' => Http::response(status: 200),
        ]);

        $run = false;
        Nightwatch::ignore(function () use (&$run) {
            Http::get('https://nightwatch.laravel.com');
            DB::statement('select * from users');
            MyJob::dispatch();
            Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
            Mail::to('tim@laravel.com')->send(new MyMail('Hello Nightwatch'));
            Cache::get('foo');

            $run = true;
        });
        $this->core->finishExecution();

        $this->assertTrue($run);
        $ingest->assertWrittenTimes(0);
    }

    public function test_exceptions_and_logs_are_not_ignored()
    {
        $ingest = $this->fakeIngest();

        $run = false;
        Nightwatch::ignore(function () use (&$run) {
            report(new RuntimeException('Whoops!'));
            Log::channel('nightwatch')->info('Hello');

            $run = true;
        });
        $this->core->finishExecution();

        $this->assertTrue($run);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('exception:0.message', 'Whoops!');
        $ingest->assertLatestWrite('log:0.message', 'Hello');
    }

    public function test_ignore_prevents_dispatched_jobs_from_being_captured(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/test', function () {
            $this->assertTrue(Compatibility::getSamplingFromContext());
            MyJob::dispatch();

            $response = $this->core->ignore(function () {
                MyJob::dispatch();
                $this->assertFalse(Compatibility::getSamplingFromContext());

                return 'ok';
            });

            MyJob::dispatch();
            $this->assertTrue(Compatibility::getSamplingFromContext());

            return $response;
        });

        $response = $this->get('/test');

        $response->assertOk();
        $response->assertContent('ok');
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(5, $records);

            return true;
        });
        $ingest->assertLatestWrite('queued-job:0.name', MyJob::class);
        $ingest->assertLatestWrite('queued-job:1.name', MyJob::class);
        $ingest->assertLatestWrite('query:0.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('query:1.sql', 'insert into "jobs" ("queue", "attempts", "reserved_at", "available_at", "created_at", "payload") values (?, ?, ?, ?, ?, ?)');
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/test');
        [$first, $second, $third] = DB::table('jobs')->orderBy('id')->pluck('payload');
        if (Compatibility::$contextExists) {
            $this->assertStringContainsString('"nightwatch_should_sample":"b:1;"', $first);
            $this->assertStringContainsString('"nightwatch_should_sample":"b:0;"', $second);
            $this->assertStringContainsString('"nightwatch_should_sample":"b:1;"', $third);
        } else {
            $this->assertStringContainsString('"nightwatch_should_sample":true', $first);
            $this->assertStringContainsString('"nightwatch_should_sample":false', $second);
            $this->assertStringContainsString('"nightwatch_should_sample":true', $third);
        }
    }

    public function test_it_can_redact_cache_events(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::redactCacheEvents(function (CacheEvent $cacheEvent) {
            $cacheEvent->key = str_replace('jess@laravel.com', '***@***', $cacheEvent->key);
        });
        Nightwatch::redactCacheEvents(function (CacheEvent $cacheEvent) {
            $cacheEvent->key = str_replace('127.0.0.1', '*.*.*.*', $cacheEvent->key);
        });

        Cache::get('jess@laravel.com|127.0.0.1');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('cache-event:0.key', '***@***|*.*.*.*');
    }

    public function test_it_can_redact_commands(): void
    {
        $this->forceCommandExecutionState();
        $this->refreshApplication();
        parent::setUp();
        $this->app[ConsoleKernel::class]->rerouteSymfonyCommandEvents();
        $ingest = $this->fakeIngest();
        Nightwatch::redactCommands(function (Command $command) {
            $command->command = str_replace('jess@laravel.com', '***@***', $command->command);
        });
        Nightwatch::redactCommands(function (Command $command) {
            $command->command = str_replace('secret123', '***', $command->command);
        });
        Artisan::command('mail:send {--email=} {--token=}', fn () => 0);

        $status = Artisan::handle($input = new StringInput('mail:send --email=jess@laravel.com --token=secret123'));
        Artisan::terminate($input, $status);

        $this->assertSame(0, $status);
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('command:0.command', 'mail:send --email=***@*** --token=***');
    }

    public function test_it_can_redact_mail(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::redactMail(function (MailRecord $mail) {
            $mail->subject = str_replace('Jess', '****', $mail->subject);
        });
        Nightwatch::redactMail(function (MailRecord $mail) {
            $mail->subject = str_replace('Brisbane', '******', $mail->subject);
        });

        Mail::to('jess@laravel.com')->send(new MyMail('Hello Jess from Brisbane'));
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('mail:0.subject', 'Hello **** from ******');
    }

    public function test_it_can_redact_outgoing_requests(): void
    {
        $ingest = $this->fakeIngest();
        Http::fake([
            'https://api.example.com/user?email=jess@laravel.com&token=secret123' => Http::response(status: 200),
        ]);
        Nightwatch::redactOutgoingRequests(function (OutgoingRequest $outgoingRequest) {
            $outgoingRequest->url = str_replace('jess@laravel.com', '***@***', $outgoingRequest->url);
        });
        Nightwatch::redactOutgoingRequests(function (OutgoingRequest $outgoingRequest) {
            $outgoingRequest->url = str_replace('secret123', '***', $outgoingRequest->url);
        });

        Http::get('https://api.example.com/user?email=jess@laravel.com&token=secret123');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('outgoing-request:0.url', 'https://api.example.com/user?email=***@***&token=***');
    }

    public function test_it_can_redact_queries(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::redactQueries(function (Query $query) {
            $query->sql = str_replace('jess@laravel.com', '***@***', $query->sql);
        });
        Nightwatch::redactQueries(function (Query $query) {
            $query->sql = str_replace('secret', '***', $query->sql);
        });

        DB::statement('select * from users where email = "jess@laravel.com" or password = "secret"');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('query:0.sql', 'select * from users where email = "***@***" or password = "***"');
    }

    public function test_it_can_redact_requests(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::redactRequests(function (Request $request) {
            $request->url = str_replace('jess@laravel.com', '***@***', $request->url);
        });
        Nightwatch::redactRequests(function (Request $request) {
            $request->url = str_replace('secret', '***', $request->url);
        });
        Nightwatch::redactRequests(function (Request $request) {
            $request->ip = preg_replace('/\d{1,3}\.\d{1,3}$/', '*.*', $request->ip);
        });
        Route::get('/test/{email}', function () {
            return 'ok';
        });

        $this->get('/test/jess@laravel.com?token=secret');

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.url', 'http://localhost/test/***@***?token=***');
        $ingest->assertLatestWrite('request:0.ip', '127.0.*.*');
    }

    public function test_it_restores_context_sampling_state_when_ignoring(): void
    {
        Compatibility::addSamplingToContext(true);

        Nightwatch::ignore(fn () => null);

        $this->assertTrue(Compatibility::getSamplingFromContext());

        Compatibility::addSamplingToContext(false);

        Nightwatch::ignore(fn () => null);

        $this->assertFalse(Compatibility::getSamplingFromContext());
    }
}
