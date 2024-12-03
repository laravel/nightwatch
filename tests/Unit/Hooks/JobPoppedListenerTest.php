<?php

use Illuminate\Queue\Events\JobPopped;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\JobPoppedListener;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    $clock = tap(new Clock, function ($clock) {
        $clock->microtimeResolver = fn () => throw new RuntimeException('Whoops!');
    });

    $state = new CommandState(
        timestamp: microtime(true),
        trace: (string) Str::uuid(),
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: $clock,
    );

    $handler = new JobPoppedListener($state);

    Log::shouldReceive('critical')
        ->once()
        ->with('[nightwatch] Whoops!');

    $handler(new JobPopped('redis', new FakeJob));
});
