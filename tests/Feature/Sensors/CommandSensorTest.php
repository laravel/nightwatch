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
    Env::getRepository()->set('NIGHTWATCH_FORCE_REQUEST', '0');
});

beforeEach(function () {
    app(CommandState::class)->deploy = 'v1.2.3';
    app(CommandState::class)->server = 'web-01';
    app(CommandState::class)->peakMemoryResolver = fn () => 1234;
    app(CommandState::class)->trace = '00000000-0000-0000-0000-000000000000';
    app(CommandState::class)->timestamp = (float) ($timestamp = CarbonImmutable::parse('2000-01-01 01:02:03.456789'))->format('U.u');
    travelTo($timestamp);
    app(CommandState::class)->stageDurations[ExecutionStage::Bootstrap->value] = 0;
    app(CommandState::class)->stage = ExecutionStage::Action;
    app(CommandState::class)->currentExecutionStageStartedAtMicrotime = (float) $timestamp->format('U.u');
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
            '_group' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
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

it('handles commands run via fuzzy matching, e.g., "build" and not "app:build"')->todo();

it('handles commands run with confirmation, e.g., "gelp" and not "help"')->todo();

it('handles commands called within a request')->todo();
