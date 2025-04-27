<?php

use App\Jobs\MyJob;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nightwatch\Compatibility;

uses(WithConsoleEvents::class);

beforeAll(function () {
    forceCommandExecutionState();
});

it('samples job attempts', function () {
    Config::set('queue.default', 'database');
    $ingest = fakeIngest();
    Compatibility::addHiddenContext('nightwatch_should_sample', false);

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }
    Artisan::call('queue:work', [
        '--max-jobs' => 10,
        '--sleep' => 0,
        '--stop-when-empty' => true,
        '--tries' => 1,
    ]);

    $ingest->assertWrittenTimes(0);
    expect(nightwatch()->state->records)->toHaveCount(0);

    Compatibility::addHiddenContext('nightwatch_should_sample', true);

    for ($i = 0; $i < 10; $i++) {
        MyJob::dispatch();
    }
    Artisan::call('queue:work', [
        '--max-jobs' => 10,
        '--sleep' => 0,
        '--stop-when-empty' => true,
        '--tries' => 1,
    ]);

    $ingest->assertWrittenTimes(10);

    expect(nightwatch()->state->records)->toHaveCount(1);
    $ingest->write(nightwatch()->state->records->pull());
    $ingest->assertLatestWrite('cache-event:0.key', 'illuminate:queue:restart');
    $ingest->assertLatestWrite('job-attempt:*', []);
});
