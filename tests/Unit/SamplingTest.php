<?php

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Notifications\MyNotification;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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
    nightwatch()->sampling['requests'] = 0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureRequestSampling();
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toBe(0);

    nightwatch()->sampling['requests'] = 0.25;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureRequestSampling();
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toEqualWithDelta(250, 50);

    nightwatch()->sampling['requests'] = 0.5;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureRequestSampling();
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toEqualWithDelta(500, 50);

    nightwatch()->sampling['requests'] = 1.0;
    $sampled = 0;

    for ($i = 0; $i < 1000; $i++) {
        nightwatch()->configureRequestSampling();
        if (nightwatch()->shouldSample) {
            $sampled++;
        }
    }

    expect($sampled)->toBe(1000);
});

it('samples queries', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        DB::table('users')->get();
    }

    expect(nightwatch()->state->queries)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        DB::table('users')->get();
    }

    expect(nightwatch()->state->queries)->toBe(10);
});

it('samples notifications', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
    }

    expect(nightwatch()->state->notifications)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Notification::route('mail', 'phillip@laravel.com')->notify(new MyNotification);
    }

    expect(nightwatch()->state->notifications)->toBe(10);
});

it('samples mail', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Mail::to('tim@laravel.com')->send(new MyMail);
    }

    expect(nightwatch()->state->mail)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Mail::to('tim@laravel.com')->send(new MyMail);
    }

    expect(nightwatch()->state->mail)->toBe(10);
});

it('samples cache', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Cache::get('foo');
    }

    expect(nightwatch()->state->cacheEvents)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Cache::get('foo');
    }

    expect(nightwatch()->state->cacheEvents)->toBe(10);
});

it('samples exceptions', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        report('Whoops!');
    }

    expect(nightwatch()->state->exceptions)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        report('Whoops!');
    }

    expect(nightwatch()->state->exceptions)->toBe(10);

    forgetRecordedExceptions(10);
});

it('samples queued jobs', function () {
    Config::set('queue.default', 'database');
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }

    expect(nightwatch()->state->jobsQueued)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }

    expect(nightwatch()->state->jobsQueued)->toBe(10);
});

it('samples outgoing requests', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    Http::fake([
        'https://nightwatch.laravel.com' => Http::response(status: 200),
    ]);

    for ($i = 0; $i < 10; $i++) {
        Http::get('https://nightwatch.laravel.com');
    }

    expect(nightwatch()->state->outgoingRequests)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Http::get('https://nightwatch.laravel.com');
    }

    expect(nightwatch()->state->outgoingRequests)->toBe(10);
});

it('samples stage', function () {
    nightwatch()->stage(ExecutionStage::Bootstrap);

    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    nightwatch()->stage(ExecutionStage::Render);

    expect(nightwatch()->state->stage)->toBe(ExecutionStage::Bootstrap);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    nightwatch()->stage(ExecutionStage::Render);

    expect(nightwatch()->state->stage)->toBe(ExecutionStage::Render);
});

it('samples remembering user', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();
    $user = new GenericUser(['id' => 123, 'remember_token' => '']);

    Auth::login($user);
    Auth::logout();

    expect(nightwatch()->state->user->id()->jsonSerialize())->toBe('');

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    Auth::login($user);
    Auth::logout();

    expect(nightwatch()->state->user->id()->jsonSerialize())->toBe('123');
});

it('samples user', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();
    Auth::login(new GenericUser(['id' => 123, 'remember_token' => '']));

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->captureUser();
    }

    expect(json_decode(nightwatch()->state->records->pull()))->toBe([]);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->captureUser();
    }

    $users = collect(json_decode(nightwatch()->state->records->pull()));
    expect($users)->toHaveCount(10);
    expect($users->pluck('id')->every(fn ($id) => $id === '123'))->toBeTrue();
});

it('samples requests', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();
    $request = Request::create('https://laravel.com');
    $response = new Response;

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->request($request, $response);
    }

    expect(json_decode(nightwatch()->state->records->pull()))->toBe([]);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        nightwatch()->request($request, $response);
    }

    $requests = collect(json_decode(nightwatch()->state->records->pull()));
    expect($requests)->toHaveCount(10);
    expect($requests->pluck('url')->every(fn ($url) => $url === 'https://laravel.com/'))->toBeTrue();
});

it('samples logs', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Log::channel('nightwatch')->info('Hello world');
    }

    expect(nightwatch()->state->logs)->toBe(0);

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        Log::channel('nightwatch')->info('Hello world');
    }

    expect(nightwatch()->state->logs)->toBe(10);
});

it('does not attach route middleware when not sampling', function ($terminatingEventExists, $expectedMiddleware) {
    Compatibility::$terminatingEventExists = $terminatingEventExists;
    fakeIngest();
    nightwatch()->sampling['requests'] = 0.0;
    nightwatch()->configureRequestSampling();
    $middleware = [];
    Route::get('/test', function () use (&$middleware) {
        $middleware = request()->route()->middleware();
    });

    for ($i = 0; $i < 10; $i++) {
        get('test')->assertOk();

        expect($middleware)->toBe([]);
    }

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();

    for ($i = 0; $i < 10; $i++) {
        get('test')->assertOk();

        expect($middleware)->toBe($expectedMiddleware);
    }
})->with([
    [$terminatingEventExists = true, [RouteMiddleware::class]],
    [$terminatingEventExists = false, [GlobalMiddleware::class, RouteMiddleware::class]],
]);

it('samples capuring request preview', function () {
    fakeIngest();
    nightwatch()->sampling['requests'] = 0.0;
    nightwatch()->configureRequestSampling();
    Route::get('/test', function () {
        //
    });

    get('test')->assertOk();

    expect(nightwatch()->state->executionPreview)->toBe('');

    nightwatch()->sampling['requests'] = 1.0;
    nightwatch()->configureRequestSampling();
    app()->forgetScopedInstances();

    get('test')->assertOk();

    expect(nightwatch()->state->executionPreview)->toBe('GET /test');
});

it('samples ingest', function () {
    $ingest = fakeIngest();

    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();
    nightwatch()->state->records->write(new User(
        timestamp: microtime(true),
        id: '123',
        name: '',
        username: '',
    ));
    nightwatch()->ingest();

    expect(nightwatch()->state->records)->toHaveCount(1);
    $ingest->assertWrittenTimes(0);

    nightwatch()->sampling['requests'] = 1;
    nightwatch()->configureRequestSampling();
    nightwatch()->state->records->write(new User(
        timestamp: microtime(true),
        id: '123',
        name: '',
        username: '',
    ));
    nightwatch()->ingest();

    expect(nightwatch()->state->records)->toHaveCount(0);
    $ingest->assertWrittenTimes(1);
});

it('discards records captured before sampling rate decided', function () {
    DB::table('users')->get();
    nightwatch()->sampling['requests'] = 0.0;
    $count = null;
    Route::get('/test', function () use (&$count) {
        $count = nightwatch()->state->records->count();
    });

    get('test')->assertOk();

    expect($count)->toBe(0);
});

it('adds context for job sampling', function () {
    nightwatch()->sampling['requests'] = 0;
    nightwatch()->configureRequestSampling();

    $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

    expect($shouldSample)->toBe(false);

    nightwatch()->sampling['requests'] = 1;
    nightwatch()->configureRequestSampling();

    $shouldSample = Compatibility::getHiddenContext('nightwatch_should_sample');

    expect($shouldSample)->toBe(true);
});
