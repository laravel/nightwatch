<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    forceRequestExecutionState();
});

beforeEach(function () {
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
});

it('captures authenticated users', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);
    $user = User::make([
        'id' => '567',
        'name' => 'Tim MacDonald',
        'email' => 'tim@laravel.com',
    ]);

    $response = actingAs($user)->get('/users');

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
});

it('handles non-eloquent user objects with no email or username', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);
    $user = new GenericUser([
        'id' => '567',
    ]);

    $response = actingAs($user)->get('/users');

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
});

it('does not capture guests', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('user:*', []);
});

it('can customize the capture of user details', function () {
    $ingest = fakeIngest();
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

    $response = actingAs($user)->get('/users');

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
});

it('handles authenticatable objects without name or email properties', function () {
    $ingest = fakeIngest();
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

    $response = actingAs($user)->get('/users');

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
});

it('can only collect the user id', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => []);
    $user = User::make([
        'id' => '567',
        'name' => 'Tim MacDonald',
        'email' => 'tim@laravel.com',
    ]);
    Nightwatch::user(fn (Authenticatable $user) => [
        'id' => '123',
    ]);

    $response = actingAs($user)->get('/users');

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
});
