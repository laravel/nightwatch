<?php

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Nightwatch\Hooks\JobExceptionOccurredListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function exception(Throwable $e): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $listener = new JobExceptionOccurredListener($nightwatch);
    $event = new JobExceptionOccurred(
        'redis',
        new FakeJob,
        new RuntimeException('Whoops!')
    );

    $listener($event);

    expect($sensor->thrown)->toBeTrue();
});
