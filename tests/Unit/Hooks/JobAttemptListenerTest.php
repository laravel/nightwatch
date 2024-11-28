<?php

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Hooks\JobAttemptListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function jobAttempt(JobProcessed|JobReleasedAfterException|JobFailed $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };
    $handler = new JobAttemptListener($sensor);

    $handler(new JobProcessed('redis', new FakeJob));

    expect($sensor->thrown)->toBeTrue();
});
