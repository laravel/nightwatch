<?php

use Illuminate\Foundation\Events\Terminating;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Compatibility;

use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeAll(function () {
    forceRequestExecutionState();
});

it('captures the terminating phase when the terminating event does not exist', function () {
    freezeTime();
    Compatibility::$terminatingEventExists = false;
    $ingest = fakeIngest();
    Route::get('/users', function () {
        app()->terminating(function () {
            travelTo(now()->addMicroseconds(123));
        });

        return [];
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.terminating', 123);
});

it('captures the terminating phase when the terminating event does exist', function () {
    freezeTime();
    Compatibility::$terminatingEventExists = true;
    $ingest = fakeIngest();
    Route::get('/users', function () {
        app()->terminating(function () {
            travelTo(now()->addMicroseconds(123));
        });

        return [];
    });

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite('request:0.terminating', 123);
})->skip(! class_exists(Terminating::class));
