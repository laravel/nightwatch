<?php

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Support;
use Symfony\Component\Console\Input\StringInput;

use function Pest\Laravel\travelTo;

uses(WithConsoleEvents::class);

beforeAll(function () {
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
            '_group' => hash('xxh128', 'app:build'),
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
it('filters out the queue:listen command')->todo();

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

it('only captures the first command that runs', function () {
    $ingest = fakeIngest();
    Artisan::command('child', function () {
        return 99;
    });
    Artisan::registerCommand(app(ParentCommand::class));

    $run = function () {
        $status = Artisan::handle($input = new StringInput('parent'));

        Artisan::terminate($input, $status);

        return $status;
    };

    expect($run())->toBe(0);
    $ingest->assertLatestWrite('command:*', [
        [
            'v' => 1,
            't' => 'command',
            'timestamp' => 946688523.456789,
            'deploy' => 'v1.2.3',
            'server' => 'web-01',
            '_group' => hash('xxh128', 'parent'),
            'trace_id' => '00000000-0000-0000-0000-000000000000',
            'class' => 'ParentCommand',
            'name' => 'parent',
            'command' => 'parent',
            'exit_code' => 0,
            'duration' => 0,
            'bootstrap' => 0,
            'action' => 0,
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

it('child commands do not progress the modify execution stage', function () {
    $ingest = fakeIngest();
    Artisan::command('parent', function () {
        Artisan::call('child');

        DB::table('users')->get();
    });
    Artisan::command('child', function () {
        //
    });

    $run = function () {
        $status = Artisan::handle($input = new StringInput('parent'));

        Artisan::terminate($input, $status);

        return $status;
    };

    expect($run())->toBe(0);
    $ingest->assertLatestWrite('command:0.queries', 1);
    $ingest->assertLatestWrite('query:0.execution_stage', 'action');
});

it('child commands do not progress the modify execution stage when terminating event does not exist', function () {
    $ingest = fakeIngest();
    Artisan::command('parent', function () {
        Artisan::call('child');

        DB::table('users')->get();
    });
    Artisan::command('child', function () {
        //
    });
    Support::$terminatingEventExists = false;

    $run = function () {
        $status = Artisan::handle($input = new StringInput('parent'));

        Artisan::terminate($input, $status);

        return $status;
    };

    expect($run())->toBe(0);
    $ingest->assertLatestWrite('command:0.queries', 1);
    $ingest->assertLatestWrite('query:0.execution_stage', 'action');
});

class ParentCommand extends Command
{
    public $name = 'parent';

    public function __invoke()
    {
        Artisan::call('child');
    }
}
