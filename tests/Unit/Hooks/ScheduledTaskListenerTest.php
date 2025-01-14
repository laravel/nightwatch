<?php

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Support\Facades\Schedule;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Hooks\ScheduledTaskListener;
use Laravel\Nightwatch\Ingests\Local\NullIngest;
use Laravel\Nightwatch\SensorManager;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\Types\Str;

it('gracefully handles exceptions', function () {
    $sensor = new class extends SensorManager
    {
        public bool $thrown = false;

        public function __construct() {}

        public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
        {
            $this->thrown = true;

            throw new RuntimeException('Whoops!');
        }
    };

    $state = new CommandState(
        timestamp: microtime(true),
        trace: (string) Str::uuid(),
        deploy: 'v1.0.0',
        server: 'web-01',
        currentExecutionStageStartedAtMicrotime: microtime(true),
        clock: new Clock,
    );
    $ingest = new NullIngest;

    $handler = new ScheduledTaskListener($sensor, $state, $ingest);

    $handler(new ScheduledTaskFinished(
        task: Schedule::command('php artisan inspire')->everyMinute(),
        runtime: 10.0,
    ));

    expect($sensor->thrown)->toBeTrue();
});
