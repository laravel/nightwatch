<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;
use Orchestra\Testbench\Foundation\Env;

use function Pest\Laravel\get;
use function Pest\Laravel\withoutExceptionHandling;

beforeAll(function () {
    Env::getRepository()->set('NIGHTWATCH_ENABLED', '0');
});

afterAll(function () {
    Env::getRepository()->clear('NIGHTWATCH_ENABLED');
});

it('can disable Nightwatch via the environment', function () {
    $this->assertFalse(nightwatch()->enabled());
});

it('gracefully ignores reported exceptions when nightwatch is disabled', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => Nightwatch::report(new RuntimeException));

    withoutExceptionHandling();
    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(0);
    $this->assertSame(0, nightwatch()->executionState->exceptions);
});

it('gracefully ignores logs when nightwatch is disabled', function () {
    $ingest = fakeIngest();
    Route::get('/users', fn () => Log::channel('nightwatch')->info('Hello world'));

    $response = get('/users');

    $response->assertOk();
    $ingest->assertWrittenTimes(0);
    $this->assertSame(0, nightwatch()->executionState->logs);
    $this->assertSame(0, nightwatch()->executionState->exceptions);
});
