<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;

it('ingests scheduled task events', function (string $status) {
    //
})
    ->with([
        'processed',
        'skipped',
        'failed',
    ])
    ->todo();

it('normalizes task names', function (Event $task) {
    //
})
    ->with([
        'closure' => fn () => Schedule::call(fn () => null)->everyMinute(),
        'named closure' => fn () => Schedule::call(fn () => null)->name('named-closure')->everyMinute(),
        'invokable class' => fn () => Schedule::call()->everyMinute(),
        'artisan command signature' => fn () => Schedule::command('inspire')->everyMinute(),
        'artisan command class' => fn () => Schedule::command()->everyMinute(),
        'job' => fn () => Schedule::job(new class() {})->everyMinute(),
        'job with handle method' => fn () => Schedule::call([])->everyMinute(),
        'shell command' => fn () => Schedule::exec('echo "Hello, world!"')->everyMinute(),
    ])
    ->todo();

it('resets trace ID and timestamp before each task run', function () {
    //
})->todo();
