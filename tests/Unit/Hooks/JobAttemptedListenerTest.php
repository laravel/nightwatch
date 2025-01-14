<?php

use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Hooks\JobAttemptedListener;
use Laravel\Nightwatch\Ingests\Local\NullIngest;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function jobAttempt(JobAttempted $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $ingest = new NullIngest;

    $handler = new JobAttemptedListener($nightwatch, $ingest);

    $handler(new JobAttempted('redis', new FakeJob));

    expect($sensor->thrown)->toBeTrue();
});
