<?php

namespace Tests\Unit;

use App\Mail\MyMail;
use App\Notifications\MyNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

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
}
