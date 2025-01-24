<?php

use App\Models\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    forceRequestExecutionState();
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
        'id' => '567',
        'name' => '',
        'username' => '',
    ]]);
});

it('does not capture guests')->todo();
