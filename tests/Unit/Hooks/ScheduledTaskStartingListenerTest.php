<?php

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\ScheduledTaskStartingListener;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    Str::createUuidsUsing(function () {
        throw new RuntimeException('Whoops!');
    });

    $state = new CommandState(
        timestamp: microtime(true),
        trace: '0d3ca349-e222-4982-ac23-2343692de258',
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: new Clock,
    );

    $handler = new ScheduledTaskStartingListener($state);

    Log::shouldReceive('critical')
        ->once()
        ->with('[nightwatch] Whoops!');

    $handler(new ScheduledTaskStarting(Schedule::command('php artisan inspire')));

    Str::createUuidsNormally();
});
