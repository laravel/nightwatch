<?php

namespace Tests\Feature\Sensors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
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

    public function test_it_captures_authenticated_users()
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

    public function test_it_handles_non_eloquent_user_objects_with_no_email_or_username()
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

    public function test_it_does_not_capture_guests()
    {
        $ingest = $this->fakeIngest();
        Route::get('/users', fn () => []);

        $response = $this->get('/users');

        $response->assertOk();
        $ingest->assertWrittenTimes(1);
        $ingest->assertLatestWrite('user:*', []);
    }

    public function test_it_can_customize_the_capture_of_user_details()
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
    }

    public function test_it_handles_authenticatable_objects_without_name_or_email_properties()
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

            public function setRememberToken($value)
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
    }

    public function test_it_can_only_collect_the_user_id()
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
    }

    public function test_it_it_captures_the_user_id_even_when_excluded_from_the_nightwatch_user_return_array()
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
    }
}
