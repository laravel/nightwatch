<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));

    ignoreMigrationQueries();
});


it('captures the authenticated user if they login during the request', function () {
    $ingest = fakeIngest();
    Route::post('login', function () {
        Auth::login(User::make(['id' => '567']));

        return 'ok';
    });

    $response = post('login');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.user', '567');
});

it('captures the authenticated user if they logout during the request', function () {
    $ingest = fakeIngest();
    Route::post('logout', fn () => Auth::logout());

    $response = actingAs(User::make(['id' => '567']))->post('logout');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.user', '567');
});

it('does not trigger an infinite loop when retrieving the authenticated user from the database', function () {
    $ingest = fakeIngest();
    Route::get('users', fn () => null);
    Config::set('auth.guards.db', ['driver' => 'db']);
    Auth::extend('db', fn () => new class implements Guard
    {
        use GuardHelpers;

        public function validate(array $credentials = [])
        {
            return true;
        }

        public function user()
        {
            static $count = 0;

            if (++$count > 10) {
                // Do not make this throw an exception.  Keep it as a `dd`. The
                // exception will be swollowed and will not fail the test.
                dd('Infinite loop detected: '.__FILE__.':'.__LINE__);
            }

            return User::first();
        }
    })->shouldUse('db');

    $response = get('users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.user', '');
});
