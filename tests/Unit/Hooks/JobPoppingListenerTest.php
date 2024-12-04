<?php

use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\JobPoppingListener;
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

    $handler = new JobPoppingListener($state);

    Log::shouldReceive('critical')
        ->once()
        ->with('[nightwatch] Whoops!');

    $handler(new JobPopping('redis', new FakeJob));

    Str::createUuidsNormally();
});
