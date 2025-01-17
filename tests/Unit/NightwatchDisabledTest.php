<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Foundation\Env;

use function Pest\Laravel\get;

beforeAll(function () {
    Env::getRepository()->set('NIGHTWATCH_ENABLED', '0');
});

afterAll(function () {
    Env::getRepository()->clear('NIGHTWATCH_ENABLED');
});

it('can disable Nightwatch via the environment', function () {
    expect(nightwatch()->enabled)->toBe(false);
});

it('gracefully ignores reported exceptions when nightwatch is disabled', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => Nightwatch::report(new RuntimeException));

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(0);
    expect(nightwatch()->state->exceptions)->toBe(0);
});

it('gracefully ignores logs when nightwatch is disabled', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => Log::channel('nightwatch')->info('Hello world'));

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(0);
    expect(nightwatch()->state->logs)->toBe(0);
    expect(nightwatch()->state->exceptions)->toBe(0);
});
