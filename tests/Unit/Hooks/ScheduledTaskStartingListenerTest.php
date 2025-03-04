<?php

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Hooks\ScheduledTaskStartingListener;
use Laravel\Nightwatch\Types\Str;

beforeAll(function () {
    forceCommandExecutionState();
});

it('gracefully handles exceptions', function () {
    Str::createUuidsUsing(function () {
        throw new RuntimeException('Whoops!');
    });

    $handler = new ScheduledTaskStartingListener(nightwatch());

    Log::shouldReceive('critical')
        ->once()
        ->with('[nightwatch] Whoops!');

    $handler(new ScheduledTaskStarting(app(Schedule::class)->command('php artisan inspire')));

    Str::createUuidsNormally();
});
