<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

use function dd;

class UserRetrievalTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setDeploy('v1.2.3');
        $this->setServerName('web-01');
        $this->setPeakMemory(1234);
        $this->setTraceId('00000000-0000-0000-0000-000000000000');
        $this->setExecutionId('00000000-0000-0000-0000-000000000001');
        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_captures_the_authenticated_user_if_they_login_during_the_request()
    {
        $ingest = $this->fakeIngest();
        Route::post('login', function () {
            Auth::login(User::make(['id' => '567']));

            return 'ok';
        });

        $response = $this->post('login');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.user', '567');
    }

    public function test_it_captures_the_authenticated_user_if_they_logout_during_the_request()
    {
        $ingest = $this->fakeIngest();
        Route::post('logout', fn () => Auth::logout());

        $response = $this->actingAs(User::make(['id' => '567']))->post('logout');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.user', '567');
    }

    public function test_it_does_not_trigger_an_infinite_loop_when_retrieving_the_authenticated_user_from_the_database()
    {
        $ingest = $this->fakeIngest();
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

        $response = $this->get('users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('request:0.user', '');
    }
}
