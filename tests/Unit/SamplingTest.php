<?php

namespace Tests\Unit;

use App\Jobs\MyJob;
use App\Mail\MyMail;
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
use Tests\TestCase;

use function collect;
use function json_decode;
use function microtime;
use function report;
use function request;

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

        $this->assertEqualsWithDelta($sampled, 250, 50);

        $this->core->config['sampling']['requests'] = 0.5;
        $sampled = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->core->configureSampling('requests');
            if ($this->core->shouldSample) {
                $sampled++;
            }
        }

        $this->assertEqualsWithDelta($sampled, 500, 50);

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

    public function test_it_samples_notifications(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_samples_mail(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_samples_cache(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_samples_exceptions(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_samples_outgoing_requests(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_samples_stage(): void
    {
        $this->core->stage(ExecutionStage::Bootstrap);

        $this->core->config['sampling']['requests'] = 0;
        $this->core->configureSampling('requests');

        $this->core->stage(ExecutionStage::Render);

        $this->assertSame(ExecutionStage::Bootstrap, $this->core->executionState->stage);

        $this->core->config['sampling']['requests'] = 1.0;
        $this->core->configureSampling('requests');

        $this->core->stage(ExecutionStage::Render);

        $this->assertSame(ExecutionStage::Render, $this->core->executionState->stage);
    }

    public function test_it_samples_remembering_user(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_samples_logs(): void
    {
        $this->core->config['sampling']['requests'] = 0;
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

    #[DataProvider('routeMiddleware')]
    public function test_it_does_not_attach_route_middleware_when_not_sampling(bool $terminatingEventExists, array $expectedMiddleware): void
    {
        Compatibility::$terminatingEventExists = $terminatingEventExists;
        $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0.0;
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

    public function test_it_samples_capuring_request_preview(): void
    {
        $this->fakeIngest();
        $this->core->config['sampling']['requests'] = 0.0;
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

    public function test_it_samples_ingest(): void
    {
        $ingest = $this->fakeIngest();

        $this->core->config['sampling']['requests'] = 0;
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

    public function test_it_discards_records_captured_before_sampling_rate_decided(): void
    {
        DB::table('users')->get();
        $this->core->config['sampling']['requests'] = 0.0;
        $count = null;
        Route::get('/test', function () use (&$count): void {
            $count = $this->core->ingest->buffer->count();
        });

        $this->get('test')->assertOk();

        $this->assertSame(0, $count);
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
}
