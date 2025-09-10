<?php

namespace Tests\Feature\Sensors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Tests\TestCase;

class UserSensorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->forceRequestExecutionState();

        parent::setUp();

        $this->setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
    }

    public function test_it_captures_authenticated_users(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        $user = User::make([
            'id' => '567',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', [[
            'v' => 1,
            't' => 'user',
            'timestamp' => 946688523.456789,
            'id' => '567',
            'name' => 'Tim MacDonald',
            'username' => 'tim@laravel.com',
        ]]);
    }

    public function test_it_handles_non_eloquent_user_objects_with_no_email_or_username(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        $user = new GenericUser([
            'id' => '567',
        ]);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', [[
            'v' => 1,
            't' => 'user',
            'timestamp' => 946688523.456789,
            'id' => '567',
            'name' => '',
            'username' => '',
        ]]);
    }

    public function test_it_does_not_capture_guests(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', []);
    }

    public function test_it_can_customize_the_capture_of_user_details(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        $user = User::make([
            'id' => '567',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => [
            'id' => '123',
            'name' => 'Tim',
            'username' => 'timacdonald',
        ]);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', [[
            'v' => 1,
            't' => 'user',
            'timestamp' => 946688523.456789,
            'id' => '123',
            'name' => 'Tim',
            'username' => 'timacdonald',
        ]]);
        $ingest->assertLatestWrite('request:0.user', '123');
    }

    public function test_it_handles_authenticatable_objects_without_name_or_email_properties(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        $user = new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id-name';
            }

            /**
             * Get the unique identifier for the user.
             *
             * @return mixed
             */
            public function getAuthIdentifier()
            {
                return '123';
            }

            public function getAuthPasswordName()
            {
                return 'password-name';
            }

            public function getAuthPassword()
            {
                return 'hunter2';
            }

            public function getRememberToken()
            {
                return 'remember-me-token';
            }

            public function setRememberToken($value): void
            {
                //
            }

            public function getRememberTokenName()
            {
                return 'remember-me-token-name';
            }
        };

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', [[
            'v' => 1,
            't' => 'user',
            'timestamp' => 946688523.456789,
            'id' => '123',
            'name' => '',
            'username' => '',
        ]]);
        $ingest->assertLatestWrite('request:0.user', '123');
    }

    public function test_it_can_only_collect_the_user_id(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        $user = User::make([
            'id' => '567',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => [
            'id' => '123',
        ]);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', [[
            'v' => 1,
            't' => 'user',
            'timestamp' => 946688523.456789,
            'id' => '123',
            'name' => '',
            'username' => '',
        ]]);
        $ingest->assertLatestWrite('request:0.user', '123');
    }

    public function test_it_it_captures_the_user_id_even_when_excluded_from_the_nightwatch_user_return_array(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);
        $user = User::make([
            'id' => '567',
            'name' => 'Tim MacDonald',
            'email' => 'tim@laravel.com',
        ]);
        Nightwatch::user(fn (Authenticatable $user) => []);

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', [[
            'v' => 1,
            't' => 'user',
            'timestamp' => 946688523.456789,
            'id' => '567',
            'name' => '',
            'username' => '',
        ]]);
        $ingest->assertLatestWrite('request:0.user', '567');
    }

    public function test_it_gracefully_handles_exceptions_while_resolving_user_ids(): void
    {
        $ingest = $this->fakeIngest();
        Route::get('/login', function () {
            DB::statement('select * from users');

            Auth::setUser(new GenericUser([]));

            DB::statement('select * from users');

            return 'ok';
        });

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertContent('ok');
        $ingest->assertLatestWriteRecordCount(4);
        $ingest->assertLatestWrite('exception:0.message', 'Undefined array key "id"');
        $ingest->assertLatestWrite('exception:0.class', 'ErrorException');
        $ingest->assertLatestWrite('exception:0.handled', true);
        $ingest->assertLatestWrite('query:0.user', '');
        $ingest->assertLatestWrite('query:1.user', '');
        $ingest->assertLatestWrite('request:0.user', '');
    }

    public function test_it_ignores_events_occurring_while_retrieving_user_credentials(): void
    {
        $ingest = $this->fakeIngest();
        Config::set('auth.guards.cached', ['driver' => 'cached']);
        Auth::extend('cached', function () {
            return new class implements Guard
            {
                use GuardHelpers;

                public function hasUser()
                {
                    return $this->user() !== null;
                }

                public function user()
                {
                    return Cache::remember('user-123', 5, fn () => new GenericUser([
                        'id' => '123',
                    ]));
                }

                public function validate(array $credentials = [])
                {
                    return true;
                }
            };
        });

        Route::get('/login', fn () => 'ok')->middleware('auth:cached');

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertContent('ok');
        $ingest->assertLatestWriteRecordCount(4);
        $ingest->assertLatestWrite('user:0.id', '123');
        $ingest->assertLatestWrite('request:0.user', '123');
        $ingest->assertLatestWrite('cache-event:0.type', 'miss');
        $ingest->assertLatestWrite('cache-event:0.user', '123');
        $ingest->assertLatestWrite('cache-event:1.type', 'write');
        $ingest->assertLatestWrite('cache-event:1.user', '123');
    }

    public function test_it_does_not_actively_resolve_guards(): void
    {
        $this->fakeIngest();
        Route::get('/test', fn () => 'ok');

        $response = $this->get('/test');

        $response->assertOk();
        $this->assertFalse(Auth::hasResolvedGuards());
    }
}
