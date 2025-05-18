<?php

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
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\Hooks\GlobalMiddleware;
use Laravel\Nightwatch\Hooks\RouteMiddleware;
use Laravel\Nightwatch\Records\User;

use function Pest\Laravel\get;

beforeAll(function () {
    forceRequestExecutionState();
});

it('can configure request sampling', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('requests');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    $this->assertSame(0, $sampled);

    nightwatch()->config['sampling']['requests'] = 0.25;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('requests');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    $this->assertEqualsWithDelta($sampled, 250, 50);

    nightwatch()->config['sampling']['requests'] = 0.5;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('requests');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    $this->assertEqualsWithDelta($sampled, 500, 50);

    nightwatch()->config['sampling']['requests'] = 1.0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureSampling('requests');
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    $this->assertSame(1000, $sampled);
});

it('samples queries', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        DB::table('users')->get();
    }

    $this->assertSame(0, nightwatch()->executionState->queries);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        DB::table('users')->get();
    }

    $this->assertSame(10, nightwatch()->executionState->queries);
});

it('samples notifications', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
    }

    $this->assertSame(0, nightwatch()->executionState->notifications);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
    }

    $this->assertSame(10, nightwatch()->executionState->notifications);
});

it('samples mail', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Mail::to('tim@laravel.com')->send(new MyMail);
    }

    $this->assertSame(0, nightwatch()->executionState->mail);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Mail::to('tim@laravel.com')->send(new MyMail);
    }

    $this->assertSame(10, nightwatch()->executionState->mail);
});

it('samples cache', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Cache::get('foo');
    }

    $this->assertSame(0, nightwatch()->executionState->cacheEvents);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Cache::get('foo');
    }

    $this->assertSame(10, nightwatch()->executionState->cacheEvents);
});

it('samples exceptions', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        report('Whoops!');
    }

    $this->assertSame(0, nightwatch()->executionState->exceptions);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        report('Whoops!');
    }

    $this->assertSame(10, nightwatch()->executionState->exceptions);
});

it('samples queued jobs', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }

    $this->assertSame(0, nightwatch()->executionState->jobsQueued);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }

    $this->assertSame(10, nightwatch()->executionState->jobsQueued);
});

it('samples outgoing requests', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    Http::fake([
        'https://nightwatch.laravel.com' => Http::response(status: 200),
    ]);

    for ($i = 0; $i < 10; $i++) {
        Http::get('https://nightwatch.laravel.com');
    }

    $this->assertSame(0, nightwatch()->executionState->outgoingRequests);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Http::get('https://nightwatch.laravel.com');
    }

    $this->assertSame(10, nightwatch()->executionState->outgoingRequests);
});

it('samples stage', function () {
    nightwatch()->stage(ExecutionStage::Bootstrap);

    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    nightwatch()->stage(ExecutionStage::Render);

    $this->assertSame(ExecutionStage::Bootstrap, nightwatch()->executionState->stage);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    nightwatch()->stage(ExecutionStage::Render);

    $this->assertSame(ExecutionStage::Render, nightwatch()->executionState->stage);
});

it('samples remembering user', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');
    $user = new GenericUser(['id' => 123, 'remember_token' => '']);

    Auth::login($user);
    Auth::logout();

    $this->assertSame('', nightwatch()->executionState->user->id()->jsonSerialize());

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    Auth::login($user);
    Auth::logout();

    $this->assertSame('123', nightwatch()->executionState->user->id()->jsonSerialize());
});

it('samples user', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');
    Auth::login(new GenericUser(['id' => 123, 'remember_token' => '']));

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->captureUser();
    }

    $this->assertSame('[]', nightwatch()->ingest->buffer->pull()->rawPayload());

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->captureUser();
    }

    $users = collect(json_decode(nightwatch()->ingest->buffer->pull()->rawPayload()));
    $this->assertCount(10, $users);
    $this->assertTrue($users->pluck('id')->every(fn ($id) => $id === '123'));
});

it('samples requests', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');
    $request = Request::create('https://laravel.com');
    $response = new Response;

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->request($request, $response);
    }

    $this->assertSame('[]', nightwatch()->ingest->buffer->pull()->rawPayload());

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->request($request, $response);
    }

    $requests = collect(json_decode(nightwatch()->ingest->buffer->pull()->rawPayload()));
    $this->assertCount(10, $requests);
    $this->assertTrue($requests->pluck('url')->every(fn ($url) => $url === 'https://laravel.com/'));
});

it('samples logs', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Log::channel('nightwatch')->info('Hello world');
    }

    $this->assertSame(0, nightwatch()->executionState->logs);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        Log::channel('nightwatch')->info('Hello world');
    }

    $this->assertSame(10, nightwatch()->executionState->logs);
});

it('does not attach route middleware when not sampling', function ($terminatingEventExists, $expectedMiddleware) {
    Compatibility::$terminatingEventExists = $terminatingEventExists;
    fakeIngest();
    nightwatch()->config['sampling']['requests'] = 0.0;
    nightwatch()->configureSampling('requests');
    $middleware = [];
    Route::get('/test', function () use (&$middleware) {
        $middleware = request()->route()->middleware();
    });

    for ($i = 0; $i < 10; $i++) {
        get('test')->assertOk();

        $this->assertSame([], $middleware);
    }

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');

    for ($i = 0; $i < 10; $i++) {
        get('test')->assertOk();

        $this->assertSame($expectedMiddleware, $middleware);
    }
})->with([
    [$terminatingEventExists = true, [RouteMiddleware::class]],
    [$terminatingEventExists = false, [GlobalMiddleware::class, RouteMiddleware::class]],
]);

it('samples capuring request preview', function () {
    fakeIngest();
    nightwatch()->config['sampling']['requests'] = 0.0;
    nightwatch()->configureSampling('requests');
    Route::get('/test', function () {
        //
    });

    get('test')->assertOk();

    $this->assertSame('', nightwatch()->executionState->executionPreview);

    nightwatch()->config['sampling']['requests'] = 1.0;
    nightwatch()->configureSampling('requests');
    app()->forgetScopedInstances();

    get('test')->assertOk();

    $this->assertSame('GET /test', nightwatch()->executionState->executionPreview);
});

it('samples ingest', function () {
    $ingest = fakeIngest();

    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');
    nightwatch()->ingest->write(new User(
        timestamp: microtime(true),
        id: '123',
        name: '',
        username: '',
    ));
    nightwatch()->digest();

    $this->assertCount(1, nightwatch()->ingest->buffer);
    $ingest->assertWrittenTimes(0);

    nightwatch()->config['sampling']['requests'] = 1;
    nightwatch()->configureSampling('requests');
    nightwatch()->ingest->write(new User(
        timestamp: microtime(true),
        id: '123',
        name: '',
        username: '',
    ));
    nightwatch()->digest();

    $this->assertCount(0, nightwatch()->ingest->buffer);
    $ingest->assertWrittenTimes(1);
});

it('discards records captured before sampling rate decided', function () {
    DB::table('users')->get();
    nightwatch()->config['sampling']['requests'] = 0.0;
    $count = null;
    Route::get('/test', function () use (&$count) {
        $count = nightwatch()->ingest->buffer->count();
    });

    get('test')->assertOk();

    $this->assertSame(0, $count);
});

it('adds context for job sampling', function () {
    nightwatch()->config['sampling']['requests'] = 0;
    nightwatch()->configureSampling('requests');

    $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

    $this->assertFalse($shouldSample);

    nightwatch()->config['sampling']['requests'] = 1;
    nightwatch()->configureSampling('requests');

    $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

    $this->assertTrue($shouldSample);
});
