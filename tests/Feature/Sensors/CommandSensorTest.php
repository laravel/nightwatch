<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\StringInput;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\ExecutionStage;
use Laravel\Nightwatch\State\CommandState;

use function Orchestra\Testbench\Pest\defineEnvironment;
use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

defineEnvironment(function () {
    forceCommandExecutionState();
});

beforeEach(function () {
    setDeploy('v1.2.3');
    setServerName('web-01');
    setPeakMemory(1234);
    setTraceId('00000000-0000-0000-0000-000000000000');
    setExecutionId('00000000-0000-0000-0000-000000000001');
    setExecutionStart(CarbonImmutable::parse('2000-01-01 01:02:03.456789'));
});

it('can ingest commands', function () {
    $ingest = fakeIngest();
    Log::listen(dump(...));
    Artisan::command('app:build {destination} {--force} {--compress}', function () {
        travelTo(now()->addMicroseconds(1234567));

        return 3;
    });

    $status = Artisan::handle($input = new StringInput('app:build path/to/output --force'));
    Artisan::terminate($input, $status);

    expect($status)->toBe(3);
    $ingest->assertWrittenTimes(1);
    $ingest->assertLatestWrite([
        [
            'v' => 1,
            't' => 'command',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => md5('app:build'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'class' => 'Illuminate\Foundation\Console\ClosureCommand',
            'name' => 'app:build',
            'command' => 'app:build path/to/output --force',
            'exit_code' => 3,
            'duration' => 1234567,
            'bootstrap' => 0,
            'action' => 1234567,
            'terminating' => 0,
            'exceptions' => 0,
            'logs' => 0,
            'queries' => 0,
            'lazy_loads' => 0,
            'jobs_queued' => 0,
            'mail' => 0,
            'notifications' => 0,
            'outgoing_requests' => 0,
            'files_read' => 0,
            'files_written' => 0,
            'cache_events' => 0,
            'hydrated_models' => 0,
            'peak_memory_usage' => 1234,
        ],
    ]);
});

it('filters out the list command')->todo();
it('filters out the queue:work command')->todo();

it('modifies status code to value in range of 0-255', function () {
    $ingest = fakeIngest();
    $status = [
        -1,
        0,
        1,
        254,
        255,
        256,
    ];
    Artisan::command('app:build {destination} {--force} {--compress}', function () use (&$status) {
        return array_shift($status);
    });

    $run = function () {
        $status = Artisan::handle($input = new StringInput('app:build path/to/output --force'));
        Artisan::terminate($input, $status);

        return $status;
    };

    expect($run())->toBe(-1);
    $ingest->assertLatestWrite('command:0.exit_code', 255);

    expect($run())->toBe(0);
    $ingest->assertLatestWrite('command:0.exit_code', 0);

    expect($run())->toBe(1);
    $ingest->assertLatestWrite('command:0.exit_code', 1);

    expect($run())->toBe(254);
    $ingest->assertLatestWrite('command:0.exit_code', 254);

    expect($run())->toBe(255);
    $ingest->assertLatestWrite('command:0.exit_code', 255);

    expect($run())->toBe(256);
    $ingest->assertLatestWrite('command:0.exit_code', 255);
});
