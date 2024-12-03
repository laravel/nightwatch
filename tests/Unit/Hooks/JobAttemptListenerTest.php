<?php

use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\JobAttemptedListener;
use Laravel\Nightwatch\Ingests\Local\NullIngest;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function jobAttempt(JobAttempted $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $ingest = new NullIngest;
    $state = new CommandState(
        timestamp: microtime(true),
        trace: (string) Str::uuid(),
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: new Clock,
    );

    $handler = new JobAttemptedListener($sensor, $state, $ingest);

    $handler(new JobAttempted('redis', new FakeJob));

    expect($sensor->thrown)->toBeTrue();
});
