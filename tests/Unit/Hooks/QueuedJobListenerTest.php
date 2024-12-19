<?php

use Illuminate\Queue\Events\JobQueued;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Hooks\JobQueuedListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = Nightwatch::setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function queuedJob(JobQueued $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });
    $handler = new JobQueuedListener($nightwatch);

    $handler(new JobQueued('redis', 'default', '1', fn () => null, '{}', 0));

    expect($sensor->thrown)->toBeTrue();
});
