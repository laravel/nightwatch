<?php

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Hooks\ScheduledTaskListener;
use Laravel\Nightwatch\SensorManager;

it('gracefully handles exceptions', function () {
    $nightwatch = nightwatch()->setSensor($sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    });

    $handler = new ScheduledTaskListener($nightwatch);

    $handler(new ScheduledTaskFinished(
        task: Schedule::command('php artisan inspire'),
        runtime: 10.0,
    ));

    expect($sensor->thrown)->toBeTrue();
});
