<?php

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\JobProcessingListener;
use Laravel\Nightwatch\State\CommandState;

it('gracefully handles exceptions', function () {
    $clock = tap(new Clock, function ($clock) {
        $clock->microtimeResolver = fn () => throw new RuntimeException('Whoops!');
    });

    $state = new CommandState(
        timestamp: microtime(true),
        trace: '0d3ca349-e222-4982-ac23-2343692de258',
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: $clock,
    );

    $handler = new JobProcessingListener($state);

    Log::shouldReceive('critical')
        ->once()
        ->with('[nightwatch] Whoops!');

    $handler(new JobProcessing('redis', new FakeJob));
});
