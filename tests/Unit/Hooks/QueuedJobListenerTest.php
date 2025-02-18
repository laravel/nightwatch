<?php

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Laravel\Nightwatch\Hooks\QueuedJobListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function queuedJob(JobQueueing|JobQueued $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $handler = new QueuedJobListener($nightwatch);

    $handler(new JobQueued('redis', 'default', '1', fn () => null, '{}', 0));

    expect($sensor->thrown)->toBeTrue();
});
