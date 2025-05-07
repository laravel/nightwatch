<?php

use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Facades\Nightwatch;

use function Pest\Laravel\get;

beforeAll(function () {
    forceRequestExecutionState();
});

it('handles ingesting zero records', function () {
    $exceptions = [];
    Nightwatch::handleUnrecoverableExceptionsUsing(function ($e) use (&$exceptions) {
        $exceptions[] = $e;
    });
    $ingest = fakeIngest();
    nightwatch()->sensor->requestSensor = fn () => throw new RuntimeException('Whoops request!');
    nightwatch()->sensor->exceptionSensor = fn () => throw new RuntimeException('Whoops exception!');
    Route::get('/users', fn () => []);

    $response = get('/users');

    $response->assertOk();
    expect($exceptions)->toHaveCount(1);
    expect($exceptions[0]->getMessage())->toBe('Whoops exception!');
    expect($ingest->latestWriteAsString())->toBe('[]');
});
