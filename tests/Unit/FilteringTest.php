<?php

namespace Tests\Unit;

use App\Mail\MyMail;
use App\Notifications\MyNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Nightwatch\Records\Mail as MailRecord;
use Laravel\Nightwatch\Records\Notification as NotificationRecord;
use Laravel\Nightwatch\Records\OutgoingRequest;
use Laravel\Nightwatch\Records\Query;
use Tests\TestCase;

use function array_shift;
use function str_contains;

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
        Nightwatch::interceptQueries(function (Query $query) {
            return str_contains($query->sql, 'users');
        });

        DB::statement('select * from users');
        DB::statement('select * from jobs');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'select * from users');
    }

    public function test_it_filters_queries_when_null_is_returned(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::interceptQueries(function (Query $query) {
            //
        });

        DB::statement('select * from users');
        DB::statement('select * from jobs');
        $ingest->digest();

        $ingest->assertWrittenTimes(0);
    }

    public function test_it_can_modify_queries_while_filtering(): void
    {
        $ingest = $this->fakeIngest();
        Nightwatch::interceptQueries(function (Query $query) {
            $query->sql = 'Hello World';

            return true;
        });

        DB::statement('select * from users');
        DB::statement('select * from jobs');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(2, $records);

            return true;
        });
        $ingest->assertLatestWrite('query:0.sql', 'Hello World');
        $ingest->assertLatestWrite('query:0.sql', 'Hello World');
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
        $keep = [true, false];
        Nightwatch::interceptNotifications(function (NotificationRecord $notification) use (&$keep) {
            return array_shift($keep);
        });

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
        Nightwatch::interceptMail(function (MailRecord $mail) {
            return $mail->subject === 'Hello Nightwatch';
        });

        Mail::to('tim@laravel.com')->send(new MyMail('Hello Laravel'));
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
        Nightwatch::interceptCacheEvents(function (CacheEvent $cacheEvent) {
            return str_contains($cacheEvent->key, 'keep');
        });

        Cache::get('keep');
        Cache::get('forget');
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
            'https://nightwatch.laravel.com' => Http::response(status: 200),
            'https://laravel.com' => Http::response(status: 200),
        ]);
        Nightwatch::interceptOutgoingRequests(function (OutgoingRequest $outgoingRequest) {
            return $outgoingRequest->host === 'laravel.com';
        });

        Http::get('https://laravel.com');
        Http::get('https://nightwatch.laravel.com');
        $ingest->digest();

        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite(function ($records) {
            $this->assertCount(1, $records);

            return true;
        });
        $ingest->assertLatestWrite('outgoing-request:0.host', 'laravel.com');
    }

    public function test_it_handles_exceptions_when_intercepting(): void
    {
        //
    }
}
