<?php

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Hooks\ScheduledTaskStartingListener;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    Str::createUuidsUsing(function () {
        throw new RuntimeException('Whoops!');
    });

    $handler = new ScheduledTaskStartingListener(nightwatch());

    Log::shouldReceive('critical')
        ->once()
        ->with('[nightwatch] Whoops!');

    $handler(new ScheduledTaskStarting(Schedule::command('php artisan inspire')));

    Str::createUuidsNormally();
});
